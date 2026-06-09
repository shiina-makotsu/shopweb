<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\ForumSection;
use App\Models\ForumThread;
use App\Models\MediaAsset;
use App\Services\ForumActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForumController extends Controller
{
    public function index(): View
    {
        return view('forum.index', [
            'sections' => ForumSection::query()
                ->active()
                ->with('moderators')
                ->withCount(['threads' => fn ($query) => $query->visible()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'latestThreads' => ForumThread::query()
                ->visible()
                ->with(['section', 'user'])
                ->orderByDesc('is_pinned')
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function section(ForumSection $section): View
    {
        abort_unless($section->is_active, 404);

        return view('forum.section', [
            'section' => $section->load('moderators'),
            'threads' => $section->threads()
                ->visible()
                ->with('user')
                ->withCount(['comments' => fn ($query) => $query->visible()])
                ->orderByDesc('is_pinned')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function show(ForumSection $section, ForumThread $thread): View
    {
        abort_unless($section->is_active && $thread->forum_section_id === $section->id && $thread->deleted_at === null, 404);

        return view('forum.show', [
            'section' => $section->load('moderators'),
            'thread' => $thread->load([
                'user',
                'comments' => fn ($query) => $query->visible()->whereNull('parent_id')->with(['user', 'replies.user'])->oldest(),
            ]),
            'canManageThread' => $thread->canBeManagedBy(auth()->user()),
        ]);
    }

    public function storeThread(Request $request, ForumSection $section, ForumActivityLogger $logger): RedirectResponse
    {
        abort_unless($section->is_active, 404);

        $data = $this->validateThread($request);
        $attachmentPaths = $this->storeAttachments($request, 'attachments');

        $baseSlug = Str::slug($data['title']) ?: 'thread';
        $slug = $baseSlug;
        $index = 2;

        while ($section->threads()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$index++;
        }

        $thread = $section->threads()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $slug,
            'body' => $data['body'],
            'attachment_paths' => $attachmentPaths,
        ]);

        $logger->log('thread_created', $request->user(), $thread, "发布帖子：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', '帖子已发布。');
    }

    public function updateThread(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);
        abort_unless($thread->canBeManagedBy($request->user()), 403);

        $data = $this->validateThread($request);
        $thread->update([
            'title' => $data['title'],
            'body' => $data['body'],
            'edited_at' => now(),
        ]);

        $logger->log('thread_updated', $request->user(), $thread, "修改帖子：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', '帖子已更新。');
    }

    public function deleteThread(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);
        abort_unless($thread->canBeManagedBy($request->user()), 403);

        $thread->update([
            'deleted_at' => now(),
            'deleted_by_id' => $request->user()->id,
        ]);

        $logger->log('thread_deleted', $request->user(), $thread, "删除帖子：{$thread->title}");

        return redirect()->route('forum.sections.show', $section)->with('status', '帖子已删除。');
    }

    public function togglePin(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);
        abort_unless($request->user()->isForumModeratorFor($section), 403);

        $thread->update(['is_pinned' => ! $thread->is_pinned]);

        $logger->log($thread->is_pinned ? 'thread_pinned' : 'thread_unpinned', $request->user(), $thread, "切换置顶：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', $thread->is_pinned ? '帖子已置顶。' : '帖子已取消置顶。');
    }

    public function likeThread(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);

        $thread->increment('likes_count');
        $logger->log('thread_liked', $request->user(), $thread, "点赞帖子：{$thread->title}");

        return back()->with('status', '已点赞。');
    }

    public function shareThread(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);

        $thread->increment('shares_count');
        $logger->log('thread_shared', $request->user(), $thread, "转发帖子：{$thread->title}");

        return back()->with('status', '已记录转发。');
    }

    public function storeComment(Request $request, ForumSection $section, ForumThread $thread, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureThread($section, $thread);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:forum_comments,id'],
            'body' => ['required', 'string', 'max:6000'],
            'attachments.*' => $this->attachmentRules(),
        ]);

        if (! empty($data['parent_id'])) {
            $parent = ForumComment::query()->where('id', $data['parent_id'])->visible()->firstOrFail();
            abort_unless($parent->forum_thread_id === $thread->id, 422);
        }

        $comment = $thread->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'attachment_paths' => $this->storeAttachments($request, 'attachments'),
        ]);

        $logger->log('comment_created', $request->user(), $comment, "回复帖子：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', '回复已发布。');
    }

    public function updateComment(Request $request, ForumSection $section, ForumThread $thread, ForumComment $comment, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureComment($section, $thread, $comment);
        abort_unless($comment->canBeManagedBy($request->user()), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:6000'],
        ]);

        $comment->update([
            'body' => $data['body'],
            'edited_at' => now(),
        ]);

        $logger->log('comment_updated', $request->user(), $comment, "修改回复：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', '回复已更新。');
    }

    public function deleteComment(Request $request, ForumSection $section, ForumThread $thread, ForumComment $comment, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureComment($section, $thread, $comment);
        abort_unless($comment->canBeManagedBy($request->user()), 403);

        $comment->update([
            'deleted_at' => now(),
            'deleted_by_id' => $request->user()->id,
        ]);

        $logger->log('comment_deleted', $request->user(), $comment, "删除回复：{$thread->title}");

        return redirect()->route('forum.threads.show', [$section, $thread])->with('status', '回复已删除。');
    }

    public function likeComment(Request $request, ForumSection $section, ForumThread $thread, ForumComment $comment, ForumActivityLogger $logger): RedirectResponse
    {
        $this->ensureComment($section, $thread, $comment);

        $comment->increment('likes_count');
        $logger->log('comment_liked', $request->user(), $comment, "点赞回复：{$thread->title}");

        return back()->with('status', '已点赞。');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateThread(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:12000'],
            'attachments.*' => $this->attachmentRules(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function attachmentRules(): array
    {
        return [
            'file',
            'max:10240',
            'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/zip,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function storeAttachments(Request $request, string $field): array
    {
        if (! $request->hasFile($field)) {
            return [];
        }

        $paths = [];

        foreach ($request->file($field) as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store('forum', 'public_uploads');
            $paths[] = $path;

            MediaAsset::query()->create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'path' => $path,
                'disk' => 'public_uploads',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'usage' => MediaAsset::USAGE_FORUM,
                'library' => MediaAsset::LIBRARY_FORUM_USER,
                'uploaded_by_id' => $request->user()?->id,
                'notes' => '论坛上传附件',
            ]);
        }

        return $paths;
    }

    private function ensureThread(ForumSection $section, ForumThread $thread): void
    {
        abort_unless($section->is_active && $thread->forum_section_id === $section->id && $thread->deleted_at === null, 404);
    }

    private function ensureComment(ForumSection $section, ForumThread $thread, ForumComment $comment): void
    {
        $this->ensureThread($section, $thread);

        abort_unless($comment->forum_thread_id === $thread->id && $comment->deleted_at === null, 404);
    }
}
