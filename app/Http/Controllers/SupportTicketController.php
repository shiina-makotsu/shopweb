<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Services\SupportChatService;
use App\Services\SupportNotificationService;
use App\Support\Money;
use App\Support\OrderStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        return $this->chatView($request, null, $this->selectedOrder($request));
    }

    public function showSession(Request $request, SupportChatSession $session): View
    {
        $this->authorizeSession($request, $session);
        abort_if($session->isClosed(), 404);

        return $this->chatView($request, $session, $this->selectedOrder($request));
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $order = $this->selectedOrder($request, $request->input('order_id'));
        $product = $this->selectedProduct($request);
        $session = $this->createSession($request, $order);

        if ($product) {
            $message = $this->sendProductContextMessage($request, $session, $product);
            app(SupportNotificationService::class)->notifyPendingMessage($session->fresh(), $message);
        }

        return redirect()
            ->route('support.sessions.show', $session)
            ->with('status', '已打开新的客服会话窗口。');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:consultation,complaint,after_sale,other'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $order = $this->selectedOrder($request, $data['order_id'] ?? null);

        SupportTicket::query()->create([
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'guest_id' => $request->user() ? null : $this->guestId($request),
            'guest_email' => $request->user() ? null : ($data['guest_email'] ?? null),
            'category' => $data['category'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return back()->with('status', '客服工单已提交，后台客服会在处理后显示回复。');
    }

    public function sendMessage(Request $request, SupportChatService $chat, SupportNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer'],
            'support_chat_session_id' => ['nullable', 'integer'],
            'include_order' => ['nullable', 'boolean'],
        ]);

        $order = $this->selectedOrder($request, $data['order_id'] ?? null);
        $body = $this->messageBody($data['message'] ?? null, $order, (bool) ($data['include_order'] ?? false));

        abort_if($body === null && ! $request->hasFile('attachment'), 422);

        $session = $this->sessionForMessage($request, $data['support_chat_session_id'] ?? null, $order);
        $wasEnded = $session->endIfIdle() || $session->isEnded();

        abort_if($session->isClosed(), 403);

        if ($wasEnded) {
            $session->forceFill([
                'status' => SupportChatSession::STATUS_OPEN,
                'ended_at' => null,
                'deleted_by_customer_at' => null,
            ])->save();
        }

        if (($data['guest_email'] ?? null) && ! $request->user()) {
            $session->update(['guest_email' => $data['guest_email']]);
        }

        if ($order && ! $session->order_id) {
            $session->update(['order_id' => $order->id]);
        }

        $attachment = $this->storeAttachment($request, $session);

        $message = $session->messages()->create([
            'sender_user_id' => $request->user()?->id,
            'sender_type' => $request->user() ? SupportChatMessage::SENDER_CUSTOMER : SupportChatMessage::SENDER_GUEST,
            'body' => $body,
            ...$attachment,
        ]);

        if (filled($body)) {
            $chat->applyQuickReplyRules($session, (string) $body);
        }

        $session->update([
            'status' => $session->assigned_admin_id ? SupportChatSession::STATUS_ACTIVE : SupportChatSession::STATUS_OPEN,
            'last_message_at' => now(),
            'deleted_by_customer_at' => null,
        ]);

        $notifications->notifyPendingMessage($session->fresh(), $message);

        return redirect()
            ->route('support.sessions.show', $session)
            ->with('status', '消息已发送。');
    }

    public function messages(Request $request, SupportChatSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        abort_if($session->isClosed(), 404);

        $session->endIfIdle();
        app(SupportChatService::class)->comfortIfIdle($session);
        $this->markAdminMessagesRead($request, $session);
        $session->refresh()->load(['messages.sender', 'assignedAdmin', 'order', 'user']);

        return response()->json([
            'html' => view('support.partials.messages', [
                'session' => $session,
                'mineMode' => 'customer',
                'emptyText' => '暂无消息。点击底部加号可以附带订单、图片或文件。',
            ])->render(),
            'last_message_id' => $session->messages->max('id') ?? 0,
            'status' => $session->status,
            'status_label' => $this->statusLabel($session->status),
            'assigned_admin' => $session->assignedAdmin?->displayName() ?? '尚未接入',
        ]);
    }

    public function destroySession(Request $request, SupportChatSession $session, SupportChatService $chat): RedirectResponse
    {
        $this->authorizeSession($request, $session);
        $chat->closeByCustomer($session);

        return redirect()
            ->route('support.index')
            ->with('status', '当前会话窗口已关闭并从你的列表中删除。');
    }

    public function attachment(Request $request, SupportChatMessage $message): StreamedResponse|Response
    {
        $message->loadMissing('session');

        if ($request->user()?->isBackofficeUser()) {
            $disk = Storage::disk('support_attachments');
            abort_unless($message->attachment_path && $disk->exists($message->attachment_path), 404);

            return $disk->response($message->attachment_path, $message->attachment_original_name);
        }

        $this->authorizeSession($request, $message->session);

        abort_unless($message->attachment_path, 404);
        $disk = Storage::disk('support_attachments');
        abort_unless($disk->exists($message->attachment_path), 404);

        return $disk->response($message->attachment_path, $message->attachment_original_name);
    }

    public function demand(Request $request): View
    {
        $guestId = $this->guestId($request);
        $selectedOrder = $this->selectedOrder($request);

        return view('support.demands', [
            'tickets' => SupportTicket::query()
                ->with('order')
                ->when($request->user(), fn ($query) => $query->whereBelongsTo($request->user()))
                ->when(! $request->user(), fn ($query) => $query->where('guest_id', $guestId))
                ->latest()
                ->paginate(10),
            'guestId' => $request->user() ? null : $guestId,
            'selectedOrder' => $selectedOrder,
            'orders' => $this->userOrders($request),
        ]);
    }

    private function selectedOrder(Request $request, mixed $orderId = null): ?Order
    {
        if (! $request->user()) {
            return null;
        }

        $id = $orderId ?? $request->query('order_id');

        if (! $id) {
            return null;
        }

        return Order::query()
            ->whereBelongsTo($request->user())
            ->whereNull('user_deleted_at')
            ->whereKey($id)
            ->first();
    }

    private function selectedProduct(Request $request): ?Product
    {
        $productId = $request->input('product_id');

        if (! $productId) {
            return null;
        }

        return Product::query()
            ->publiclyVisible()
            ->whereKey($productId)
            ->first();
    }

    private function guestId(Request $request): string
    {
        if (! $request->session()->has('support_guest_id')) {
            $request->session()->put('support_guest_id', 'guest_'.strtolower(bin2hex(random_bytes(5))));
        }

        return (string) $request->session()->get('support_guest_id');
    }

    private function chatView(Request $request, ?SupportChatSession $session = null, ?Order $selectedOrder = null): View
    {
        $session ??= $this->currentSession($request, $selectedOrder);
        $session->endIfIdle();
        app(SupportChatService::class)->comfortIfIdle($session);
        $this->markAdminMessagesRead($request, $session);
        $session->load(['messages.sender', 'assignedAdmin', 'order']);
        $settings = SiteSetting::query()->first();

        $sessions = $this->visibleSessions($request)
            ->with(['assignedAdmin', 'order'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        if (! $sessions->contains($session)) {
            $sessions->prepend($session);
        }

        return view('support.index', [
            'session' => $session,
            'sessions' => $sessions,
            'tickets' => SupportTicket::query()
                ->with('order')
                ->when($request->user(), fn ($query) => $query->whereBelongsTo($request->user()))
                ->when(! $request->user(), fn ($query) => $query->where('guest_id', $this->guestId($request)))
                ->latest()
                ->paginate(10),
            'guestId' => $request->user() ? null : $this->guestId($request),
            'selectedOrder' => $selectedOrder,
            'orders' => $this->userOrders($request),
            'supportAiEnabled' => (bool) ($settings?->support_ai_enabled ?? false),
            'supportAiIdleMinutes' => max(1, (int) ($settings?->support_ai_idle_minutes ?: 10)),
        ]);
    }

    private function currentSession(Request $request, ?Order $order = null): SupportChatSession
    {
        if ($order) {
            $session = $this->visibleSessions($request)
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();

            if ($session) {
                return $session;
            }
        }

        $session = $this->visibleSessions($request)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($session) {
            return $session;
        }

        return $this->createSession($request, $order);
    }

    private function sessionForMessage(Request $request, mixed $sessionId, ?Order $order = null): SupportChatSession
    {
        if ($sessionId) {
            return $this->visibleSessions($request)->whereKey($sessionId)->firstOrFail();
        }

        return $this->currentSession($request, $order);
    }

    private function createSession(Request $request, ?Order $order = null): SupportChatSession
    {
        return SupportChatSession::query()->create([
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'guest_id' => $request->user() ? null : $this->guestId($request),
            'status' => SupportChatSession::STATUS_OPEN,
            'last_message_at' => now(),
        ]);
    }

    private function sendProductContextMessage(Request $request, SupportChatSession $session, Product $product): SupportChatMessage
    {
        $product->loadMissing('variants');

        $message = $session->messages()->create([
            'sender_user_id' => $request->user()?->id,
            'sender_type' => $request->user() ? SupportChatMessage::SENDER_CUSTOMER : SupportChatMessage::SENDER_GUEST,
            'body' => $this->productMessage($product),
        ]);

        $session->update([
            'status' => SupportChatSession::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        return $message;
    }

    private function visibleSessions(Request $request): Builder
    {
        $query = SupportChatSession::query()
            ->whereNull('deleted_by_customer_at')
            ->whereIn('status', [
                SupportChatSession::STATUS_OPEN,
                SupportChatSession::STATUS_ACTIVE,
                SupportChatSession::STATUS_ENDED,
            ]);

        if ($request->user()) {
            $query->whereBelongsTo($request->user());
        } else {
            $query->where('guest_id', $this->guestId($request));
        }

        return $query;
    }

    private function userOrders(Request $request): Collection
    {
        if (! $request->user()) {
            return collect();
        }

        return $request->user()
            ->orders()
            ->whereNull('user_deleted_at')
            ->latest()
            ->limit(50)
            ->get(['id', 'order_number', 'status', 'total_cents', 'created_at']);
    }

    private function messageBody(?string $message, ?Order $order, bool $includeOrder): ?string
    {
        $body = trim((string) $message);

        if (! $includeOrder || ! $order) {
            return $body === '' ? null : $body;
        }

        $orderText = $this->orderMessage($order);

        return trim($body === '' ? $orderText : $body."\n\n".$orderText);
    }

    private function orderMessage(Order $order): string
    {
        $order->loadMissing('items');

        $status = app(OrderStatusPresenter::class)->label($order->status);
        $total = Money::format($order->total_cents);
        $items = $order->items
            ->map(fn ($item): string => "- {$item->product_title} x {$item->quantity}")
            ->implode("\n");

        return trim(<<<TEXT
订单信息：
订单号：{$order->order_number}
订单状态：{$status}
订单金额：{$total}
商品：
{$items}
TEXT);
    }

    private function productMessage(Product $product): string
    {
        $price = $product->starting_price_cents !== null
            ? Money::format($product->starting_price_cents)
            : '暂无价格';
        $url = $product->showUrl();
        $summary = trim((string) $product->summary);

        return trim(<<<TEXT
商品咨询：
商品：{$product->title}
状态：{$product->statusLabel()}
价格：{$price}
链接：{$url}
{$summary}
TEXT);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            SupportChatSession::STATUS_ACTIVE => '接待中',
            SupportChatSession::STATUS_ENDED => '已结束',
            SupportChatSession::STATUS_CLOSED => '用户已关闭',
            default => '等待接入',
        };
    }

    private function markAdminMessagesRead(Request $request, SupportChatSession $session): void
    {
        if ($request->user()?->isBackofficeUser()) {
            return;
        }

        $session->messages()
            ->where('sender_type', SupportChatMessage::SENDER_ADMIN)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeAttachment(Request $request, SupportChatSession $session): array
    {
        if (! $request->hasFile('attachment')) {
            return [];
        }

        $file = $request->file('attachment');
        $path = $file->store('session-'.$session->id, 'support_attachments');

        return [
            'attachment_path' => $path,
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_mime_type' => $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    private function authorizeSession(Request $request, SupportChatSession $session): void
    {
        if ($request->user()) {
            abort_unless($session->user_id === $request->user()->id || $request->user()->isBackofficeUser(), 403);

            return;
        }

        abort_unless($session->guest_id === $this->guestId($request), 403);
    }
}
