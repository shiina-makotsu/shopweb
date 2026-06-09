<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'settings' => SiteSetting::query()->first(),
            'featuredProducts' => Product::query()
                ->published()
                ->where('is_featured', true)
                ->with(['coverMedia', 'variants'])
                ->orderBy('sort_order')
                ->limit(8)
                ->get(),
            'discountProducts' => Product::query()
                ->published()
                ->whereHas('variants', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('discount_price_cents')
                    ->where(fn ($query) => $query->whereNull('discount_starts_at')->orWhere('discount_starts_at', '<=', now()))
                    ->where(fn ($query) => $query->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>=', now())))
                ->with(['coverMedia', 'variants'])
                ->latest()
                ->limit(8)
                ->get(),
            'latestProducts' => Product::query()
                ->published()
                ->with(['coverMedia', 'variants'])
                ->latest()
                ->limit(8)
                ->get(),
            'incomingProducts' => Product::query()
                ->where('status', Product::STATUS_INCOMING)
                ->with(['coverMedia', 'variants'])
                ->latest()
                ->limit(8)
                ->get(),
            'conceptProducts' => Product::query()
                ->where('status', Product::STATUS_CONCEPT)
                ->with(['coverMedia', 'variants'])
                ->latest()
                ->limit(8)
                ->get(),
            'flashSales' => FlashSale::query()
                ->with(['product.coverMedia', 'product.variants'])
                ->whereHas('product', fn ($query) => $query->where('status', Product::STATUS_PUBLISHED))
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->orderByRaw('case when starts_at <= ? then 0 else 1 end', [now()])
                ->orderBy('starts_at')
                ->limit(8)
                ->get(),
            'pages' => Page::query()->published()->orderBy('sort_order')->limit(6)->get(),
        ]);
    }
}
