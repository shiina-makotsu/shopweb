<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $readIds = $request->user()
            ? AnnouncementRead::query()->where('user_id', $request->user()->id)->pluck('announcement_id')->all()
            : [];

        return view('announcements.index', [
            'announcements' => Announcement::query()
                ->published()
                ->orderByDesc('is_pinned')
                ->latest('published_at')
                ->latest()
                ->paginate(10),
            'readIds' => $readIds,
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        abort_unless($announcement->is_published, 404);

        if ($request->user()) {
            AnnouncementRead::query()->updateOrCreate([
                'announcement_id' => $announcement->id,
                'user_id' => $request->user()->id,
            ], [
                'read_at' => now(),
            ]);
        }

        return view('announcements.show', [
            'announcement' => $announcement,
        ]);
    }
}
