<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FlashSale;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StorefrontCache
{
    private const SETTINGS_KEY = 'shop:storefront:site-settings:first';
    private const CATEGORIES_KEY = 'shop:storefront:categories:active';
    private const PAGES_KEY = 'shop:storefront:pages:menuable';
    private const MENU_KEY_PREFIX = 'shop:storefront:menus:';
    private const HOME_KEY_PREFIX = 'shop:storefront:home:';

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return [
            self::SETTINGS_KEY,
            self::CATEGORIES_KEY,
            self::PAGES_KEY,
            self::MENU_KEY_PREFIX.NavigationMenuItem::PLACEMENT_TOP_NAV,
            self::MENU_KEY_PREFIX.NavigationMenuItem::PLACEMENT_HOME_INFO,
            self::HOME_KEY_PREFIX.'featured',
            self::HOME_KEY_PREFIX.'discount',
            self::HOME_KEY_PREFIX.'latest',
            self::HOME_KEY_PREFIX.'concept',
            self::HOME_KEY_PREFIX.'flash-sales',
        ];
    }

    public function settings(): ?SiteSetting
    {
        return Cache::remember(self::SETTINGS_KEY, now()->addMinutes(10), function (): ?SiteSetting {
            if (! $this->tableReady('site_settings')) {
                return null;
            }

            return SiteSetting::query()->first();
        });
    }

    public function categories(): Collection
    {
        return Cache::remember(self::CATEGORIES_KEY, now()->addMinutes(10), function (): Collection {
            if (! $this->tableReady('categories')) {
                return collect();
            }

            return Category::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });
    }

    public function pages(): Collection
    {
        return Cache::remember(self::PAGES_KEY, now()->addMinutes(10), function (): Collection {
            if (! $this->tableReady('pages')) {
                return collect();
            }

            return Page::query()
                ->published()
                ->menuable()
                ->orderBy('sort_order')
                ->orderBy('title')
                ->limit(8)
                ->get();
        });
    }

    public function menuItems(string $placement): Collection
    {
        return Cache::remember(self::MENU_KEY_PREFIX.$placement, now()->addMinutes(10), function () use ($placement): Collection {
            if (! $this->tableReady('navigation_menu_items')) {
                return collect();
            }

            $hasPlacementColumn = Schema::hasColumn('navigation_menu_items', 'placement');

            return NavigationMenuItem::query()
                ->active()
                ->when($hasPlacementColumn, fn ($query) => $query->placement($placement))
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query
                    ->active()
                    ->when($hasPlacementColumn, fn ($query) => $query->placement($placement))
                    ->orderBy('sort_order')
                    ->orderBy('label')])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();
        });
    }

    public function homeProducts(string $section): Collection
    {
        return Cache::remember(self::HOME_KEY_PREFIX.$section, now()->addMinutes(3), function () use ($section): Collection {
            if (! $this->tableReady('products')) {
                return collect();
            }

            $visibleStatuses = [
                Product::STATUS_PRESALE,
                Product::STATUS_INCOMING,
                Product::STATUS_PUBLISHED,
                Product::STATUS_SOLD_OUT,
            ];

            $query = Product::query()
                ->whereIn('status', $section === 'concept' ? [Product::STATUS_CONCEPT] : $visibleStatuses)
                ->with(['coverMedia', 'variants']);

            return match ($section) {
                'featured' => $query
                    ->where('is_featured', true)
                    ->orderBy('sort_order')
                    ->limit(8)
                    ->get(),
                'discount' => $query
                    ->whereHas('variants', fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNotNull('discount_price_cents')
                        ->where(fn ($query) => $query->whereNull('discount_starts_at')->orWhere('discount_starts_at', '<=', now()))
                        ->where(fn ($query) => $query->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>=', now())))
                    ->latest()
                    ->limit(8)
                    ->get(),
                default => $query
                    ->orderBy('sort_order')
                    ->oldest()
                    ->limit(8)
                    ->get(),
            };
        });
    }

    public function flashSales(): Collection
    {
        return Cache::remember(self::HOME_KEY_PREFIX.'flash-sales', now()->addMinute(), function (): Collection {
            if (! $this->tableReady('flash_sales') || ! $this->tableReady('products')) {
                return collect();
            }

            return FlashSale::query()
                ->with(['product.coverMedia', 'product.variants'])
                ->whereHas('product', fn ($query) => $query->whereIn('status', [Product::STATUS_PUBLISHED, Product::STATUS_PRESALE]))
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->orderByRaw('case when starts_at <= ? then 0 else 1 end', [now()])
                ->orderBy('starts_at')
                ->limit(8)
                ->get();
        });
    }

    /**
     * @return array<int, string>
     */
    public function prewarm(): array
    {
        $this->settings();
        $this->categories();
        $this->pages();
        $this->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV);
        $this->menuItems(NavigationMenuItem::PLACEMENT_HOME_INFO);
        $this->homeProducts('featured');
        $this->homeProducts('discount');
        $this->homeProducts('latest');
        $this->homeProducts('concept');
        $this->flashSales();

        return self::keys();
    }

    public function clear(): void
    {
        foreach (self::keys() as $key) {
            Cache::forget($key);
        }
    }

    private function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
