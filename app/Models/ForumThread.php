<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumThread extends Model
{
    protected $fillable = [
        'forum_section_id',
        'user_id',
        'title',
        'slug',
        'template',
        'body',
        'attachment_paths',
        'is_pinned',
        'is_locked',
        'is_featured',
        'likes_count',
        'shares_count',
        'views_count',
        'deleted_at',
        'deleted_by_id',
        'edited_at',
        'last_replied_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_featured' => 'boolean',
            'attachment_paths' => 'array',
            'deleted_at' => 'datetime',
            'edited_at' => 'datetime',
            'last_replied_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ForumSection::class, 'forum_section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ForumThreadRead::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $user !== null
            && ($user->id === $this->user_id || $user->isForumModeratorFor($this->section));
    }

    public function canReceiveReplies(): bool
    {
        return ! $this->is_locked && $this->deleted_at === null;
    }

    public function latestActivityAt()
    {
        return $this->last_replied_at ?? $this->updated_at ?? $this->created_at;
    }
}
