<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\ShippingQuoteService;
use App\Support\ChinaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request, CartService $cart, ShippingQuoteService $shippingQuotes, CouponService $coupons): View|RedirectResponse
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
            'regionTree' => ChinaRegions::regionTreeForForms(),
            'shippingProvince' => $shippingQuote['province'],
            'shippingQuote' => $shippingQuote,
            'availableCouponsByVariant' => $coupons->availableForCart($request->user(), $cart->items()),
        ]);
    }

    public function store(Request $request, CartService $cart, OrderService $orders): RedirectResponse
    {
        $requiresShipping = $cart->requiresShipping();
        $input = $request->all();

        if ($requiresShipping) {
            $input = $this->fillShippingAddress($input);
        }

        $data = validator($input, [
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'shipping_address' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:500'],
            'shipping_country' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:100'],
            'shipping_province' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:50'],
            'shipping_city' => [$requiresShipping ? 'required' : 'nullable', 'string', 'max:100'],
            'shipping_district' => ['nullable', 'string', 'max:100'],
            'shipping_street' => ['nullable', 'string', 'max:100'],
            'shipping_detail' => ['nullable', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'coupon_items' => ['nullable', 'array'],
            'coupon_items.*' => ['nullable', 'integer', 'exists:user_coupons,id'],
        ])->validate();

        $data['requires_shipping'] = $requiresShipping;

        $order = $orders->createFromCart($request->user(), $data);

        return redirect()->route('orders.show', $order)->with('status', '订单已创建，请按页面说明付款并上传凭证。');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fillShippingAddress(array $input): array
    {
        $parsed = ChinaRegions::parseAddress((string) ($input['shipping_address'] ?? ''));

        foreach ([
            'shipping_country' => 'country',
            'shipping_city' => 'city',
            'shipping_district' => 'district',
            'shipping_street' => 'street',
            'shipping_detail' => 'detail',
        ] as $field => $parsedField) {
            if (blank($input[$field] ?? null) && filled($parsed[$parsedField] ?? null)) {
                $input[$field] = $parsed[$parsedField];
            }
        }

        if (blank($input['shipping_country'] ?? null)) {
            $input['shipping_country'] = '中国';
        }

        if (blank($input['shipping_city'] ?? null) && filled($input['shipping_province'] ?? null)) {
            $normalizedProvince = ChinaRegions::normalizeProvince($input['shipping_province']) ?? (string) $input['shipping_province'];
            $cities = ChinaRegions::regionTreeForForms()[$normalizedProvince] ?? [];

            if (count($cities) === 1) {
                $input['shipping_city'] = array_key_first($cities);
            }
        }

        return $input;
    }
}
