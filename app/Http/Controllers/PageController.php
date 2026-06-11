<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\PageTemplate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request, Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('pages.show', [
            ...PageTemplate::viewData($page->loadMissing('coverMediaAsset'), $request),
            'templateView' => PageTemplate::viewName($page->template),
        ]);
    }
}
