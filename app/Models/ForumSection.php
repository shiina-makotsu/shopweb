<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumSection extends Model
{
    public const POSTING_ALL = 'all';
    public const POSTING_BACKOFFICE = 'backoffice';
    public const POSTING_MEMBER = 'member';
    public const POSTING_MODERATOR = 'moderator';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'posting_policy',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ForumSectionRead::class);
    }

    public function latestActivityAt()
    {
        return $this->last_thread_activity_at ?? $this->updated_at ?? $this->created_at;
    }

    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forum_moderators')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function canBePostedBy(?User $user): bool
    {
        if (! $user || $user->isForumPostingBanned()) {
            return false;
        }

        return match ($this->posting_policy ?: self::POSTING_ALL) {
            self::POSTING_BACKOFFICE => $user->isBackofficeUser(),
            self::POSTING_MEMBER => $user->account_type === 'member' || $user->isBackofficeUser(),
            self::POSTING_MODERATOR => $user->isForumModeratorFor($this),
            default => true,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function postingPolicyOptions(): array
    {
        return [
            self::POSTING_ALL => '所有登录用户',
            self::POSTING_BACKOFFICE => '仅后台用户',
            self::POSTING_MEMBER => '仅会员/后台用户',
            self::POSTING_MODERATOR => '仅版主/后台用户',
        ];
    }
}
