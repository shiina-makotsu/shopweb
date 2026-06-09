<?php

namespace App\Models;

use App\Support\AdminAccess;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'public_id',
        'email',
        'password',
        'role',
        'account_type',
        'forum_role',
        'nickname',
        'avatar_path',
        'profile_intro',
        'preferred_locale',
        'interface_settings',
        'privacy_settings',
        'can_view_order_numbers',
        'can_view_tracking_numbers',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'interface_settings' => 'array',
            'privacy_settings' => 'array',
            'can_view_order_numbers' => 'boolean',
            'can_view_tracking_numbers' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if (filled($user->public_id)) {
                return;
            }

            $user->forceFill([
                'public_id' => static::uniquePublicId($user),
            ])->saveQuietly();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return AdminAccess::canAccessPanel($this);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminAccess::ROLE_ADMIN;
    }

    public function isBackofficeUser(): bool
    {
        return AdminAccess::canAccessPanel($this);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function intentVotes(): HasMany
    {
        return $this->hasMany(ProductIntentVote::class);
    }

    public function priceVotes(): HasMany
    {
        return $this->hasMany(ProductPriceVote::class);
    }

    public function browsingHistories(): HasMany
    {
        return $this->hasMany(ProductBrowsingHistory::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(ProductWishlist::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ProductFavorite::class);
    }

    public function productComments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function procurementAllocations(): HasMany
    {
        return $this->hasMany(ProcurementUserAllocation::class);
    }

    public function afterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class);
    }

    public function moderatedForumSections(): BelongsToMany
    {
        return $this->belongsToMany(ForumSection::class, 'forum_moderators')->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'recipient_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function displayName(): string
    {
        return $this->nickname ?: $this->name;
    }

    public function isForumModeratorFor(ForumSection $section): bool
    {
        return $this->role === 'admin'
            || $this->moderatedForumSections()->whereKey($section->id)->exists();
    }

    private static function uniquePublicId(User $user): string
    {
        $base = $user->email === 'admin@example.com'
            ? 'admin'
            : 'user_'.($user->id ?: Str::lower(Str::random(8)));

        $publicId = $base;
        $index = 2;

        while (static::query()->where('public_id', $publicId)->whereKeyNot($user->id)->exists()) {
            $publicId = $base.'_'.$index++;
        }

        return $publicId;
    }
}
