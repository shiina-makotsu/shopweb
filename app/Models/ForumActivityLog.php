<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumActivityLog extends Model
{
    private const ACTION_LABELS = [
        'thread_created' => '发布帖子',
        'thread_updated' => '修改帖子',
        'thread_deleted' => '删除帖子',
        'thread_pinned' => '置顶帖子',
        'thread_unpinned' => '取消置顶帖子',
        'thread_liked' => '点赞帖子',
        'thread_shared' => '转发帖子',
        'comment_created' => '回复帖子',
        'comment_updated' => '修改回复',
        'comment_deleted' => '删除回复',
        'comment_liked' => '点赞回复',
        'comment_deleted_admin' => '后台删除回复',
        'thread_pinned_admin' => '后台置顶帖子',
        'thread_unpinned_admin' => '后台取消置顶',
        'thread_featured_admin' => '后台星标帖子',
        'thread_unfeatured_admin' => '后台取消星标',
        'thread_locked_admin' => '后台锁定帖子',
        'thread_unlocked_admin' => '后台解锁帖子',
        'thread_noted_admin' => '后台备注帖子',
        'thread_deleted_admin' => '后台删除帖子',
    ];

    protected $fillable = [
        'forum_section_id',
        'forum_thread_id',
        'forum_comment_id',
        'actor_user_id',
        'target_user_id',
        'action',
        'target_type',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ForumSection::class, 'forum_section_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ForumComment::class, 'forum_comment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? str($this->action)
            ->replace(['_', '.'], ' ')
            ->headline()
            ->toString();
    }
}
