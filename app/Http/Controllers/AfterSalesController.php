<?php

namespace App\Http\Controllers;

use App\Models\AfterSalesRequest;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\Money;
use App\Support\OrderPrivacy;
use App\Support\OrderStatusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AfterSalesController extends Controller
{
    public function create(Request $request, Order $order, OrderPrivacy $privacy): View
    {
        $this->authorizeOrder($request, $order);

        return view('orders.after-sales', [
            'order' => $order->load(['items', 'afterSalesRequests']),
            'settings' => SiteSetting::query()->first(),
            'privacy' => $privacy,
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'type' => ['required', 'in:refund,repair,shipping,other'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        AfterSalesRequest::query()->create([
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'type' => $data['type'],
            'status' => AfterSalesRequest::STATUS_OPEN,
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        return redirect()
            ->route('orders.after-sales', $order)
            ->with('status', '售后需求已提交，后台客服会在处理后留言。');
    }

    public function contactSupport(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $order->loadMissing(['items', 'shippingCarrier']);

        return redirect()
            ->route('support.index', ['order_id' => $order->id])
            ->with('status', '已打开客服会话，你可以直接发送订单相关问题。');
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($request->user() && $order->user_id === $request->user()->id, 403);
        abort_if($order->user_deleted_at, 404);
    }

    private function supportMessage(Order $order): string
    {
        $status = app(OrderStatusPresenter::class)->label($order->status);
        $total = Money::format($order->total_cents);
        $items = $order->items
            ->map(fn ($item): string => "- {$item->product_title} x {$item->quantity}")
            ->implode("\n");

        return trim(<<<TEXT
我需要咨询这个订单的售后问题。

订单号：{$order->order_number}
订单状态：{$status}
订单金额：{$total}
物流单号：{$order->tracking_number}

商品：
{$items}
TEXT);
    }
}
