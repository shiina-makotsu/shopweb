<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumComment extends Model
{
    protected $fillable = [
        'forum_thread_id',
        'user_id',
        'parent_id',
        'body',
        'attachment_paths',
        'likes_count',
        'deleted_at',
        'deleted_by_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'attachment_paths' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->whereNull('deleted_at')->oldest();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $thread = $this->thread;

        return $user->id === $this->user_id
            || $user->id === $thread->user_id
            || $user->isForumModeratorFor($thread->section);
    }
}
