<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementCommentController extends Controller
{
    public function store(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->is_published && $announcement->comments_enabled, 404);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:announcement_comments,id'],
            'body' => ['required', 'string', 'max:3000'],
        ]);

        if (! empty($data['parent_id'])) {
            $parent = AnnouncementComment::query()->visible()->findOrFail($data['parent_id']);
            abort_unless($parent->announcement_id === $announcement->id, 422);
        }

        AnnouncementComment::query()->create([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        return back()->with('status', '评论已提交。');
    }
}
