<?php

namespace App\Http\Controllers;

use App\Models\FriendLink;
use Illuminate\View\View;

class FriendLinkController extends Controller
{
    public function index(): View
    {
        return view('friend-links.index', [
            'links' => FriendLink::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('site_name')
                ->paginate(36),
        ]);
    }
}
