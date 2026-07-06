<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Services\FlashSaleService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'privateShippingDefault' => $item->flashSale->product->defaultsToPrivateShipping(),
        ]);
    }

    public function store(Request $request, Order $order, FlashSaleService $service, OrderService $orders): RedirectResponse
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
            'private_shipping_requested' => ['nullable', 'boolean'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:'.implode(',', [
                Order::PAYMENT_METHOD_QR_CODE,
                Order::PAYMENT_METHOD_RED_PACKET,
                Order::PAYMENT_METHOD_WALLET,
                Order::PAYMENT_METHOD_PAYPAL,
            ])],
        ]);

        $data['payment_method'] ??= Order::PAYMENT_METHOD_QR_CODE;
        $data['private_shipping_requested'] = $requiresShipping && (bool) ($data['private_shipping_requested'] ?? false);

        if ($data['payment_method'] === Order::PAYMENT_METHOD_PAYPAL && ! SiteSetting::query()->first()?->paypalEmail()) {
            throw ValidationException::withMessages(['payment_method' => 'PayPal 收款邮箱未配置，暂不能使用 PayPal 支付。']);
        }

        $order = $service->completeOrderSelection($order, $data);

        if ((int) $order->total_cents === 0 && $order->payment_status !== Order::PAYMENT_CONFIRMED) {
            $orders->confirmPayment($order, $request->user());
            $order = $order->fresh() ?? $order;
        }

        return redirect()->route('orders.show', $order)
            ->with('status', '秒杀订单已创建，请按页面说明付款并上传凭证。');
    }
}
