<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\AlertBotService;
use App\Services\OrderService;
use App\Services\PaymentProofStorage;
use App\Support\OrderPrivacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request, OrderPrivacy $privacy): View
    {
        $settings = SiteSetting::query()->first();

        return view('orders.index', [
            'orders' => Order::query()
                ->whereBelongsTo($request->user())
                ->whereNull('user_deleted_at')
                ->latest()
                ->paginate(12),
            'settings' => $settings,
            'privacy' => $privacy,
        ]);
    }

    public function show(Request $request, Order $order, OrderPrivacy $privacy): View
    {
        $this->authorizeVisibleOrder($request, $order);
        $settings = SiteSetting::query()->first();

        if ($order->hasDigitalDelivery()) {
            app(OrderService::class)->markDigitalDeliveryViewed($order, $request->user());
        }

        return view('orders.show', [
            'order' => $order->load(['items.incomingProduct', 'items.product', 'shippingCarrier']),
            'settings' => $settings,
            'privacy' => $privacy,
        ]);
    }

    public function uploadProof(Request $request, Order $order, OrderService $orders, PaymentProofStorage $proofStorage): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        $data = $request->validate([
            'payment_proof' => ['nullable', 'image', 'max:5120'],
            'payment_text_proof' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $request->hasFile('payment_proof') && blank($data['payment_text_proof'] ?? null)) {
            throw ValidationException::withMessages([
                'payment_proof' => '请上传付款截图，或填写口令红包/文字付款凭证。',
            ]);
        }

        $order = $order->fresh();

        abort_unless($order, 404);

        if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
            return back()->with('status', '订单已确认付款，无需重复提交。');
        }

        if ($order->payment_status === Order::PAYMENT_SUBMITTED && ($order->payment_proof_path || $order->payment_text_proof)) {
            return back()
                ->with('payment_success', true)
                ->with('status', '付款信息已提交，请勿重复操作。');
        }

        $path = $order->payment_proof_path;
        $oldPath = $order->payment_proof_path;
        $storedNewProof = false;

        if ($request->hasFile('payment_proof')) {
            try {
                $storedPath = $proofStorage->store($order, $data['payment_proof']);
            } catch (Throwable $exception) {
                Log::error('Payment proof upload failed.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'payment_proof' => '付款凭证保存失败，请稍后重试，或改用口令红包/文字付款凭证。',
                ]);
            }

            if (! is_string($storedPath) || $storedPath === '') {
                Log::error('Payment proof upload returned an empty path.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);

                throw ValidationException::withMessages([
                    'payment_proof' => '付款凭证保存失败，请稍后重试，或改用口令红包/文字付款凭证。',
                ]);
            }

            $path = $storedPath;
            $storedNewProof = true;
        }

        try {
            $orders->markPaymentSubmitted($order, $path, $data['payment_text_proof'] ?? null);
        } catch (Throwable $exception) {
            if ($storedNewProof) {
                $proofStorage->delete($path);
            }

            Log::error('Payment proof submission failed.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment_proof' => '付款信息提交失败，请稍后重试；如果重复出现，请联系客服处理。',
            ]);
        }

        if ($storedNewProof && $oldPath && $oldPath !== $path) {
            $proofStorage->delete($oldPath);
        }
        app(AlertBotService::class)->notify('ShopWeb P3 订单待确认收款', '用户已提交付款信息，订单等待后台确认收款。', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $order->user_id,
            'total_cents' => $order->total_cents,
            'payment_method' => $order->payment_method,
        ], 'P3');

        return back()->with('payment_success', true);
    }

    public function switchPaymentMethod(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        abort_unless($order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status !== Order::PAYMENT_CONFIRMED, 404);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:'.implode(',', [
                Order::PAYMENT_METHOD_QR_CODE,
                Order::PAYMENT_METHOD_FALLBACK_QR,
                Order::PAYMENT_METHOD_RED_PACKET,
                Order::PAYMENT_METHOD_PAYPAL,
            ])],
        ]);

        if ($data['payment_method'] === Order::PAYMENT_METHOD_PAYPAL && ! SiteSetting::query()->first()?->paypalEmail()) {
            throw ValidationException::withMessages(['payment_method' => 'PayPal 收款邮箱未配置，暂不能使用 PayPal 支付。']);
        }

        return DB::transaction(function () use ($order, $data): RedirectResponse {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
                return back()->with('status', '订单已确认付款，无需重复切换支付方式。');
            }

            if ($order->payment_status === Order::PAYMENT_SUBMITTED) {
                return back()->with('status', '付款信息已提交，不能再切换支付方式。');
            }

            $order->forceFill([
                'payment_method' => $data['payment_method'],
            ])->save();

            return back()->with('status', '支付方式已切换为 '.$order->fresh()->paymentMethodLabel().'。');
        });
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        abort_unless(
            in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED], true),
            404
        );

        if ($order->status !== Order::STATUS_CANCELLED) {
            $orders->cancel($order, $request->user());
        }

        return redirect()
            ->route('orders.index')
            ->with('status', '订单已取消。');
    }

    public function confirmReceipt(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        abort_unless($order->status === Order::STATUS_AWAITING_RECEIPT, 404);

        $orders->confirmReceipt($order, $request->user());

        return back()->with('status', '已确认收货，订单已完成。');
    }

    public function markDigitalCopied(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);
        $orders->markDigitalDeliveryViewed($order, $request->user());

        return back()->with('status', '交付内容已复制，请确认检查无误后点击确认收货。');
    }

    public function downloadDigitalAttachment(Request $request, Order $order, int $index, OrderService $orders): StreamedResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        $paths = $order->digital_delivery_attachment_paths ?: [];
        $path = $paths[$index] ?? null;

        abort_unless(is_string($path) && Storage::disk('digital_deliveries')->exists($path), 404);

        $orders->markDigitalDeliveryViewed($order, $request->user());

        return Storage::disk('digital_deliveries')->download($path, basename($path));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        $order->forceFill([
            'user_deleted_at' => now(),
        ])->save();

        return redirect()
            ->route('orders.index')
            ->with('status', '订单已从你的订单列表中删除。');
    }

    private function authorizeVisibleOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_if($order->user_deleted_at, 404);
    }
}
