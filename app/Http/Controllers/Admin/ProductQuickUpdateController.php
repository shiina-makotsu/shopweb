<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AdminActivityLogger;
use App\Support\MoneyInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductQuickUpdateController extends Controller
{
    public function update(Request $request, Product $product, AdminActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(Product::statusOptions()))],
            'is_featured' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['required', 'integer', 'exists:product_variants,id'],
            'variants.*.spec_name' => ['nullable', 'string', 'max:255'],
            'variants.*.price_cents' => ['required'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
        ]);

        $changes = [];

        DB::transaction(function () use ($product, $data, &$changes): void {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $productUpdates = [
                'title' => trim((string) $data['title']),
                'status' => $data['status'],
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ];

            foreach ($productUpdates as $field => $newValue) {
                $oldValue = $product->getAttribute($field);

                if ((string) $oldValue === (string) $newValue) {
                    continue;
                }

                $changes['product'][$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }

            if (! empty($changes['product'])) {
                $product->forceFill($productUpdates)->save();
            }

            foreach ($data['variants'] ?? [] as $variantData) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()
                    ->whereBelongsTo($product)
                    ->whereKey($variantData['id'])
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    continue;
                }

                $variantUpdates = [
                    'spec_name' => trim((string) ($variantData['spec_name'] ?? '')) ?: null,
                    'price_cents' => MoneyInput::toCents($variantData['price_cents']),
                    'stock' => (int) $variantData['stock'],
                ];

                foreach ($variantUpdates as $field => $newValue) {
                    $oldValue = $variant->getAttribute($field);

                    if ((string) $oldValue === (string) $newValue) {
                        continue;
                    }

                    $changes['variants'][$variant->id][$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }

                if (! empty($changes['variants'][$variant->id])) {
                    $variant->forceFill($variantUpdates)->save();
                }
            }
        });

        if ($changes !== []) {
            $activity->log('product_quick_updated', $product->fresh(), '后台列表快速更新商品', [
                'product_id' => $product->id,
                'changes' => $changes,
            ], $request->user());
        }

        return back()->with('status', '商品已更新。');
    }
}
