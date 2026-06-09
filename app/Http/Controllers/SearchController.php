<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Support\RegexSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = trim($request->string('q')->toString());

        $products = Product::query()
            ->publiclyVisible()
            ->with(['category', 'coverMedia', 'variants'])
            ->when($keyword !== '', fn ($query) => RegexSearch::where($query, ['title', 'summary', 'description'], $keyword))
            ->latest()
            ->paginate(8, ['*'], 'products_page')
            ->withQueryString();

        $users = User::query()
            ->where('role', 'customer')
            ->when($keyword !== '', fn ($query) => RegexSearch::where($query, ['name', 'public_id'], $keyword))
            ->latest()
            ->paginate(10, ['*'], 'users_page')
            ->withQueryString();

        return view('search.index', [
            'keyword' => $keyword,
            'products' => $products,
            'users' => $users,
        ]);
    }
}
