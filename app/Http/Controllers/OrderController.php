<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\OrderService;
use App\Support\OrderPrivacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

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

        if ($order->hasDigitalDelivery() && blank($order->digital_delivery_code) && empty($order->digital_delivery_attachment_paths)) {
            app(OrderService::class)->markDigitalDeliveryAccessed($order, $request->user());
        }

        return view('orders.show', [
            'order' => $order->load(['items', 'shippingCarrier']),
            'settings' => $settings,
            'privacy' => $privacy,
        ]);
    }

    public function uploadProof(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        $data = $request->validate([
            'payment_proof' => ['required', 'image', 'max:5120'],
        ]);

        if ($order->payment_proof_path) {
            Storage::disk('payment_proofs')->delete($order->payment_proof_path);
        }

        $path = $data['payment_proof']->store($order->order_number, 'payment_proofs');
        $orders->markPaymentSubmitted($order, $path);

        return back()->with('payment_success', true);
    }

    public function confirmReceipt(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        abort_unless($order->status === Order::STATUS_AWAITING_RECEIPT, 404);

        $orders->confirmReceipt($order, $request->user());

        return back()->with('status', '已确认签收，订单已完成。');
    }

    public function markDigitalCopied(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeVisibleOrder($request, $order);
        $orders->markDigitalDeliveryAccessed($order, $request->user());

        return back()->with('status', '交付内容已确认，订单已完成。');
    }

    public function downloadDigitalAttachment(Request $request, Order $order, int $index, OrderService $orders): StreamedResponse
    {
        $this->authorizeVisibleOrder($request, $order);

        $paths = $order->digital_delivery_attachment_paths ?: [];
        $path = $paths[$index] ?? null;

        abort_unless(is_string($path) && Storage::disk('digital_deliveries')->exists($path), 404);

        $orders->markDigitalDeliveryAccessed($order, $request->user());

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
