<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'cart.items';
    public const MAX_ITEM_QUANTITY = 9999;

    /**
     * @return array<int, int>
     */
    public function rawItems(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function add(ProductVariant $variant, int $quantity): void
    {
        $items = $this->rawItems();
        $items[$variant->id] = min(self::MAX_ITEM_QUANTITY, ($items[$variant->id] ?? 0) + max(1, $quantity));

        Session::put(self::SESSION_KEY, $items);
    }

    public function replace(ProductVariant $variant, int $quantity): void
    {
        Session::put(self::SESSION_KEY, [
            $variant->id => min(self::MAX_ITEM_QUANTITY, max(1, $quantity)),
        ]);
    }

    public function update(ProductVariant $variant, int $quantity): void
    {
        $items = $this->rawItems();

        if ($quantity <= 0) {
            unset($items[$variant->id]);
        } else {
            $items[$variant->id] = min(self::MAX_ITEM_QUANTITY, $quantity);
        }

        Session::put(self::SESSION_KEY, $items);
    }

    public function remove(ProductVariant $variant): void
    {
        $items = $this->rawItems();
        unset($items[$variant->id]);
        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    public function items(): Collection
    {
        $rawItems = $this->rawItems();

        if ($rawItems === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.coverMedia'])
            ->whereIn('id', array_keys($rawItems))
            ->get()
            ->keyBy('id');

        return collect($rawItems)
            ->map(function (int $quantity, int|string $variantId) use ($variants): ?array {
                $variant = $variants->get((int) $variantId);

                if (! $variant || ! $variant->is_active || ! $variant->product->isPurchasable()) {
                    return null;
                }

                return [
                    'variant' => $variant,
                    'product' => $variant->product,
                    'quantity' => $quantity,
                    'unit_price_cents' => $variant->effectivePriceCents(),
                    'line_total_cents' => $variant->effectivePriceCents() * $quantity,
                ];
            })
            ->filter()
            ->values();
    }

    public function subtotalCents(): int
    {
        return $this->items()->sum('line_total_cents');
    }

    public function requiresShipping(): bool
    {
        return $this->items()->contains(fn (array $item): bool => $item['product']->requiresShipping());
    }
}
