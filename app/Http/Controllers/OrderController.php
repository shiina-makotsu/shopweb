<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\OrderService;
use App\Services\WalletService;
use App\Support\OrderPrivacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function uploadProof(Request $request, Order $order, OrderService $orders): RedirectResponse
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

        if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
            return back()->with('status', '订单已确认付款，无需重复提交。');
        }

        if ($order->payment_status === Order::PAYMENT_SUBMITTED && ($order->payment_proof_path || $order->payment_text_proof)) {
            return back()
                ->with('payment_success', true)
                ->with('status', '付款信息已提交，请勿重复操作。');
        }

        if ($request->hasFile('payment_proof') && $order->payment_proof_path) {
            Storage::disk('payment_proofs')->delete($order->payment_proof_path);
        }

        $path = $request->hasFile('payment_proof')
            ? $data['payment_proof']->store($order->order_number, 'payment_proofs')
            : $order->payment_proof_path;

        $orders->markPaymentSubmitted($order, $path, $data['payment_text_proof'] ?? null);

        return back()->with('payment_success', true);
    }

    public function switchPaymentMethod(Request $request, Order $order, OrderService $orders, WalletService $wallet): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        abort_unless($order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status !== Order::PAYMENT_CONFIRMED, 404);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:'.implode(',', [
                Order::PAYMENT_METHOD_QR_CODE,
                Order::PAYMENT_METHOD_FALLBACK_QR,
                Order::PAYMENT_METHOD_RED_PACKET,
                Order::PAYMENT_METHOD_WALLET,
            ])],
        ]);

        return DB::transaction(function () use ($request, $order, $orders, $wallet, $data): RedirectResponse {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
                return back()->with('status', '订单已确认付款，无需重复切换支付方式。');
            }

            if ($order->payment_status === Order::PAYMENT_SUBMITTED) {
                return back()->with('status', '付款信息已提交，不能再切换支付方式。');
            }

            if ($data['payment_method'] === Order::PAYMENT_METHOD_WALLET && (int) $order->total_cents > 0) {
                $walletPayment = $wallet->applyAvailableBalanceToOrder($request->user(), $order, (int) $order->total_cents, $request->user());

                if (! $walletPayment) {
                    throw ValidationException::withMessages(['payment_method' => '钱包余额不足，无法使用钱包支付。']);
                }

                $walletPaymentCents = abs((int) $walletPayment->amount_cents);
                $order->forceFill([
                    'payment_method' => Order::PAYMENT_METHOD_WALLET,
                    'wallet_payment_cents' => (int) $order->wallet_payment_cents + $walletPaymentCents,
                    'total_cents' => max(0, (int) $order->total_cents - $walletPaymentCents),
                ])->save();

                if ((int) $order->fresh()->total_cents === 0) {
                    $orders->confirmPayment($order->fresh(), $request->user());

                    return back()->with('status', '钱包余额已完成支付，订单已确认付款。');
                }

                return back()->with('status', '已使用钱包余额抵扣，剩余金额请继续选择其他方式支付。');
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
