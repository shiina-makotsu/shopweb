<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\PageTemplate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function articles(Request $request): View
    {
        $sort = $request->string('sort')->toString() === 'views' ? 'views' : 'latest';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = Page::query()
            ->published()
            ->where('template', PageTemplate::ARTICLE)
            ->with('coverMediaAsset');

        if ($sort === 'views') {
            $query->orderBy('views_count', $direction)->orderBy('updated_at', 'desc');
        } else {
            $query->orderBy('created_at', $direction)->orderBy('id', $direction);
        }

        return view('pages.articles', [
            'articles' => $query->paginate(12)->withQueryString(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Request $request, Page $page): View
    {
        abort_unless($page->is_published, 404);

        if ($page->template === PageTemplate::ARTICLE) {
            $page->increment('views_count');
            $page->refresh();
            $page->loadMissing(['coverMediaAsset', 'topLevelComments.user', 'topLevelComments.replies.user']);
        }

        return view('pages.show', [
            ...PageTemplate::viewData($page->loadMissing('coverMediaAsset'), $request),
            'templateView' => PageTemplate::viewName($page->template),
        ]);
    }
}
