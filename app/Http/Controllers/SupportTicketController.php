<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $guestId = $this->guestId($request);
        $selectedOrder = $this->selectedOrder($request);
        $session = $this->currentSession($request, $selectedOrder);
        $session->endIfIdle();
        $session->load(['messages.sender', 'assignedAdmin', 'order']);

        return view('support.index', [
            'session' => $session,
            'tickets' => SupportTicket::query()
                ->with('order')
                ->when($request->user(), fn ($query) => $query->whereBelongsTo($request->user()))
                ->when(! $request->user(), fn ($query) => $query->where('guest_id', $guestId))
                ->latest()
                ->paginate(10),
            'guestId' => $request->user() ? null : $guestId,
            'selectedOrder' => $selectedOrder,
            'orders' => $request->user()
                ? $request->user()->orders()->latest()->limit(50)->get(['id', 'order_number', 'status', 'total_cents', 'created_at'])
                : collect(),
        ]);
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

        return back()->with('status', '客服会话已提交，后台处理后会在这里显示回复。');
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer'],
        ]);

        abort_if(blank($data['message'] ?? null) && ! $request->hasFile('attachment'), 422);

        $order = $this->selectedOrder($request, $data['order_id'] ?? null);
        $session = $this->currentSession($request, $order);
        $wasEnded = $session->endIfIdle() || $session->isEnded();

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

        $session->messages()->create([
            'sender_user_id' => $request->user()?->id,
            'sender_type' => $request->user() ? SupportChatMessage::SENDER_CUSTOMER : SupportChatMessage::SENDER_GUEST,
            'body' => $data['message'] ?? null,
            ...$attachment,
        ]);

        $session->update([
            'status' => $session->assigned_admin_id ? SupportChatSession::STATUS_ACTIVE : SupportChatSession::STATUS_OPEN,
            'last_message_at' => now(),
            'deleted_by_customer_at' => null,
        ]);

        return redirect()->route('support.index')->with('status', '消息已发送。');
    }

    public function destroySession(Request $request, SupportChatSession $session): RedirectResponse
    {
        $this->authorizeSession($request, $session);

        $session->update([
            'deleted_by_customer_at' => now(),
        ]);

        return redirect()->route('support.index')->with('status', '当前会话窗口已删除。');
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
            'orders' => $request->user()
                ? $request->user()->orders()->latest()->limit(50)->get(['id', 'order_number', 'status', 'total_cents', 'created_at'])
                : collect(),
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
            ->whereKey($id)
            ->first();
    }

    private function guestId(Request $request): string
    {
        if (! $request->session()->has('support_guest_id')) {
            $request->session()->put('support_guest_id', 'guest_'.strtolower(bin2hex(random_bytes(5))));
        }

        return (string) $request->session()->get('support_guest_id');
    }

    private function currentSession(Request $request, ?Order $order = null): SupportChatSession
    {
        $query = SupportChatSession::query()
            ->whereNull('deleted_by_customer_at')
            ->whereIn('status', [SupportChatSession::STATUS_OPEN, SupportChatSession::STATUS_ACTIVE, SupportChatSession::STATUS_ENDED])
            ->latest('id');

        if ($request->user()) {
            $query->whereBelongsTo($request->user());
        } else {
            $query->where('guest_id', $this->guestId($request));
        }

        $session = $query->first();

        if ($session) {
            return $session;
        }

        return SupportChatSession::query()->create([
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'guest_id' => $request->user() ? null : $this->guestId($request),
            'status' => SupportChatSession::STATUS_OPEN,
        ]);
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
