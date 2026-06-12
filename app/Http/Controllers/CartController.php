<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(CartService $cart): View
    {
        return view('cart.show', [
            'items' => $cart->items(),
            'subtotalCents' => $cart->subtotalCents(),
            'requiresShipping' => $cart->requiresShipping(),
        ]);
    }

    public function store(Request $request, CartService $cart): RedirectResponse|JsonResponse
    {
        $data = $this->validatedCartItem($request);
        $variant = $this->findPurchasableVariant((int) $data['variant_id']);

        $cart->add($variant, (int) $data['quantity']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '已加入购物车。',
                'cart_count' => $cart->items()->sum('quantity'),
                'cart_subtotal' => \App\Support\Money::format($cart->subtotalCents()),
            ]);
        }

        return redirect()->route('cart.show')->with('status', '已加入购物车。');
    }

    public function buyNow(Request $request, CartService $cart): RedirectResponse
    {
        $data = $this->validatedCartItem($request);
        $variant = $this->findPurchasableVariant((int) $data['variant_id']);

        $cart->replace($variant, (int) $data['quantity']);

        return redirect()->route('checkout.create');
    }

    public function update(Request $request, ProductVariant $variant, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_ITEM_QUANTITY],
        ]);

        $cart->update($variant, (int) $data['quantity']);

        return back()->with('status', '购物车已更新。');
    }

    public function destroy(ProductVariant $variant, CartService $cart): RedirectResponse
    {
        $cart->remove($variant);

        return back()->with('status', '商品已移出购物车。');
    }

    /**
     * @return array{variant_id:string|int, quantity:int|string}
     */
    private function validatedCartItem(Request $request): array
    {
        return $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_ITEM_QUANTITY],
        ]);
    }

    private function findPurchasableVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
        abort_unless($variant->is_active && $variant->product->isPurchasable(), 404);

        return $variant;
    }
}
