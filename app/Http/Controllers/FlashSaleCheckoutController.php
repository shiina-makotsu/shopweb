<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\Order;
use App\Services\FlashSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FlashSaleCheckoutController extends Controller
{
    public function reserve(Request $request, FlashSale $flashSale, FlashSaleService $service): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        abort_unless($flashSale->isAvailable(), 404);

        $order = $service->createReservedOrder($request->user(), $flashSale, (int) $data['quantity']);

        return redirect()->route('flash-sales.checkout', $order)
            ->with('status', '已抢到秒杀名额，请选择规格并提交订单信息。');
    }

    public function create(Order $order, FlashSaleService $service): View|RedirectResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load('items.flashSale.product.coverMedia');
        $item = $order->items->firstWhere('flash_sale_id', '!=', null);

        if (! $item) {
            return redirect()->route('orders.show', $order);
        }

        return view('flash-sales.checkout', [
            'order' => $order,
            'item' => $item,
            'flash_sale' => $item->flashSale,
            'product' => $item->flashSale->product,
            'quantity' => $item->quantity,
            'variants' => $service->variantsFor($order),
        ]);
    }

    public function store(Request $request, Order $order, FlashSaleService $service): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $order->load('items.flashSale.product');
        $item = $order->items->firstWhere('flash_sale_id', '!=', null);
        abort_unless($item, 404);

        $requiresShipping = $item->flashSale->product->requiresShipping();

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'shipping_address' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);

        $order = $service->completeOrderSelection($order, $data);

        return redirect()->route('orders.show', $order)->with('status', '秒杀订单已创建，请按页面说明付款并上传凭证。');
    }
}
