<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\ProductWishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductPreferenceController extends Controller
{
    public function toggleWishlistByStatus(Request $request, string $statusSlug, string $productSlug): RedirectResponse
    {
        $product = Product::findPublicForStatusRoute($statusSlug, $productSlug);

        abort_unless($product, 404);

        return $this->toggleWishlist($request, $product);
    }

    public function toggleWishlist(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        $entry = ProductWishlist::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($entry) {
            $entry->delete();

            return back()->with('status', '已从愿望单移除。');
        }

        ProductWishlist::query()->firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return back()->with('status', '已加入愿望单。');
    }

    public function toggleFavoriteByStatus(Request $request, string $statusSlug, string $productSlug): RedirectResponse
    {
        $product = Product::findPublicForStatusRoute($statusSlug, $productSlug);

        abort_unless($product, 404);

        return $this->toggleFavorite($request, $product);
    }

    public function toggleFavorite(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        $entry = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($entry) {
            $entry->delete();

            return back()->with('status', '已取消收藏。');
        }

        ProductFavorite::query()->firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return back()->with('status', '已收藏商品。');
    }
}
