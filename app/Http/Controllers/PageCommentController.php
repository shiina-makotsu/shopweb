<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageComment;
use App\Support\PageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageCommentController extends Controller
{
    public function store(Request $request, Page $page): RedirectResponse
    {
        abort_unless($page->is_published && $page->template === PageTemplate::ARTICLE && $page->comments_enabled, 404);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:page_comments,id'],
            'body' => ['required', 'string', 'max:3000'],
        ]);

        if (! empty($data['parent_id'])) {
            $parent = PageComment::query()->visible()->findOrFail($data['parent_id']);
            abort_unless($parent->page_id === $page->id, 422);
        }

        PageComment::query()->create([
            'page_id' => $page->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        return back()->with('status', '评论已提交。');
    }
}
