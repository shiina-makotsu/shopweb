<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ShippingQuoteService;
use App\Support\ChinaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request, CartService $cart, ShippingQuoteService $shippingQuotes): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.show')->withErrors(['cart' => '购物车为空。']);
        }

        $defaultAddress = $request->user()->addresses()->where('is_default', true)->first();
        $shippingProvince = $request->old(
            'shipping_province',
            $defaultAddress?->province ?: ChinaRegions::guessProvinceFromAddress($defaultAddress?->formatted()),
        );
        $shippingQuote = $shippingQuotes->quote($cart->items(), $shippingProvince);

        return view('checkout.create', [
            'items' => $cart->items(),
            'subtotalCents' => $cart->subtotalCents(),
            'requiresShipping' => $cart->requiresShipping(),
            'defaultAddress' => $defaultAddress,
            'provinceOptions' => ChinaRegions::provinceOptions(),
            'shippingProvince' => $shippingQuote['province'],
            'shippingQuote' => $shippingQuote,
        ]);
    }

    public function store(Request $request, CartService $cart, OrderService $orders): RedirectResponse
    {
        $requiresShipping = $cart->requiresShipping();

        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'shipping_address' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:500'],
            'shipping_province' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:50'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);

        $data['requires_shipping'] = $requiresShipping;

        $order = $orders->createFromCart($request->user(), $data);

        return redirect()->route('orders.show', $order)->with('status', '订单已创建，请按页面说明付款并上传凭证。');
    }
}
