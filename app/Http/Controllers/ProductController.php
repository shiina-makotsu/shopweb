<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBrowsingHistory;
use App\Models\ProductIntentVote;
use App\Models\ProductTag;
use App\Support\RegexSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $category = null;
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, Product::statusOptions()) && $status !== Product::STATUS_DRAFT
            ? $status
            : null;
        $query = Product::query()
            ->publiclyVisible()
            ->with(['category', 'coverMedia', 'variants'])
            ->orderBy('sort_order')
            ->latest();

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('discount')) {
            $query->whereHas('variants', fn ($query) => $query
                ->where('is_active', true)
                ->whereNotNull('discount_price_cents')
                ->where(fn ($query) => $query->whereNull('discount_starts_at')->orWhere('discount_starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>=', now())));
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($request->filled('category')) {
            $category = Category::query()->active()->where('slug', $request->string('category')->toString())->first();
            if ($category) {
                $query->whereBelongsTo($category);
            }
        }

        if ($request->filled('q')) {
            $keyword = trim($request->string('q')->toString());

            RegexSearch::where($query, ['title', 'summary', 'description'], $keyword);
        }

        return view('products.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
            'currentCategory' => $category,
            'currentStatus' => $status,
            'featuredOnly' => $request->boolean('featured'),
            'discountOnly' => $request->boolean('discount'),
            'keyword' => trim($request->string('q')->toString()),
        ]);
    }

    public function tag(Request $request, ProductTag $tag): View
    {
        abort_unless($tag->is_active, 404);

        $query = Product::query()
            ->whereHas('tags', fn ($query) => $query->whereKey($tag->id))
            ->publiclyVisible()
            ->with(['category', 'coverMedia', 'variants'])
            ->orderBy('sort_order')
            ->latest();

        if ($request->filled('q')) {
            RegexSearch::where($query, ['title', 'summary', 'description'], trim($request->string('q')->toString()));
        }

        return view('products.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
            'currentCategory' => null,
            'currentTag' => $tag,
            'keyword' => trim($request->string('q')->toString()),
        ]);
    }

    public function tags(): View
    {
        return view('tags.index', [
            'tags' => ProductTag::query()
                ->active()
                ->withCount(['products' => fn ($query) => $query->publiclyVisible()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless(in_array($product->status, [
            Product::STATUS_CONCEPT,
            Product::STATUS_PRESALE,
            Product::STATUS_INCOMING,
            Product::STATUS_PUBLISHED,
            Product::STATUS_SOLD_OUT,
        ], true), 404);

        $product->load([
            'category',
            'media',
            'tags' => fn ($query) => $query->active()->orderBy('sort_order'),
            'shippingCarrier',
            'comments' => fn ($query) => $query->visible()->whereNull('parent_id')->with(['user', 'replies.user'])->latest(),
            'variants' => fn ($query) => $query->active(),
            'priceVoteOptions' => fn ($query) => $query->active(),
        ]);

        if (auth()->check()) {
            $history = ProductBrowsingHistory::query()->firstOrNew([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ]);
            $history->view_count = $history->exists ? ((int) $history->view_count + 1) : 1;
            $history->viewed_at = now();
            $history->save();
        }

        $intentCounts = $product->allowsVoting()
            ? ProductIntentVote::query()
                ->whereBelongsTo($product)
                ->selectRaw('intent, count(*) as votes')
                ->groupBy('intent')
                ->pluck('votes', 'intent')
            : collect();

        $priceCounts = $product->allowsVoting()
            ? $product->priceVoteOptions()
                ->withCount('votes')
                ->get()
            : collect();

        return view('products.show', [
            'product' => $product,
            'intentCounts' => $intentCounts,
            'priceOptions' => $priceCounts,
            'myIntentVote' => $product->allowsVoting() && auth()->check()
                ? ProductIntentVote::query()->whereBelongsTo($product)->where('user_id', auth()->id())->first()
                : null,
            'myPriceVote' => $product->allowsVoting() && auth()->check()
                ? auth()->user()->priceVotes()->where('product_id', $product->id)->first()
                : null,
        ]);
    }
}
