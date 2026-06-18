<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    public function storeByStatus(Request $request, string $statusSlug, string $productSlug): RedirectResponse
    {
        $product = Product::findPublicForStatusRoute($statusSlug, $productSlug);

        abort_unless($product, 404);

        return $this->store($request, $product);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->comments_enabled, 404);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:product_comments,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $paths = [];

        foreach ($request->file('images', []) as $image) {
            $paths[] = $image->store('comments', 'public_uploads');
        }

        if (! empty($data['parent_id'])) {
            $parent = ProductComment::query()->visible()->findOrFail($data['parent_id']);
            abort_unless($parent->product_id === $product->id, 422);
        }

        ProductComment::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'rating' => (int) $data['rating'],
            'body' => $data['body'],
            'image_paths' => $paths,
        ]);

        return back()->with('status', '评论已提交。');
    }
}
