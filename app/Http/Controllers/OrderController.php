<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\OrderService;
use App\Support\OrderPrivacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, OrderPrivacy $privacy): View
    {
        $settings = SiteSetting::query()->first();

        return view('orders.index', [
            'orders' => Order::query()
                ->whereBelongsTo($request->user())
                ->latest()
                ->paginate(12),
            'settings' => $settings,
            'privacy' => $privacy,
        ]);
    }

    public function show(Request $request, Order $order, OrderPrivacy $privacy): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $settings = SiteSetting::query()->first();

        return view('orders.show', [
            'order' => $order->load(['items', 'shippingCarrier']),
            'settings' => $settings,
            'privacy' => $privacy,
        ]);
    }

    public function uploadProof(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'payment_proof' => ['required', 'image', 'max:5120'],
        ]);

        if ($order->payment_proof_path) {
            Storage::disk('payment_proofs')->delete($order->payment_proof_path);
        }

        $path = $data['payment_proof']->store($order->order_number, 'payment_proofs');
        $orders->markPaymentSubmitted($order, $path);

        return back()->with('status', '付款凭证已提交，等待人工确认。');
    }
}
