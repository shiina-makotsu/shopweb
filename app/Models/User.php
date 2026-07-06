<?php

namespace App\Models;

use App\Support\AdminAccess;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory;
    use Notifiable;

    public const BACKOFFICE_PUBLIC_ID_PREFIX = 'staff_';

    protected $fillable = [
        'name',
        'public_id',
        'referral_code',
        'referred_by_user_id',
        'email',
        'password',
        'role',
        'account_type',
        'forum_role',
        'forum_posting_banned_at',
        'forum_posting_ban_reason',
        'avatar_path',
        'profile_intro',
        'birthday',
        'has_diagnosis_certificate',
        'preferred_locale',
        'interface_settings',
        'privacy_settings',
        'can_view_order_numbers',
        'can_view_tracking_numbers',
        'support_email_notifications_enabled',
        'ai_quota_k',
        'ai_usage_reset_at',
        'wallet_balance_cents',
        'ai_endpoint',
        'ai_api_key',
        'ai_image_endpoint',
        'ai_image_api_key',
        'ai_chat_endpoint',
        'ai_chat_api_key',
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
            'forum_posting_banned_at' => 'datetime',
            'interface_settings' => 'array',
            'privacy_settings' => 'array',
            'can_view_order_numbers' => 'boolean',
            'can_view_tracking_numbers' => 'boolean',
            'support_email_notifications_enabled' => 'boolean',
            'birthday' => 'date',
            'has_diagnosis_certificate' => 'boolean',
            'ai_usage_reset_at' => 'datetime',
            'wallet_balance_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->exists && blank($user->public_id)) {
                return;
            }

            $user->public_id = static::publicIdForRole($user);
        });

        static::created(function (User $user): void {
            $user->ensurePublicId();
            $user->ensureReferralCode();
        });
    }

    public function ensurePublicId(): void
    {
        $publicId = static::publicIdForRole($this);

        if ($this->public_id === $publicId) {
            return;
        }

        $this->forceFill([
            'public_id' => $publicId,
        ])->saveQuietly();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return AdminAccess::canAccessPanel($this);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public_uploads')->url($this->avatar_path)
            : null;
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

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function productComments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    public function pageComments(): HasMany
    {
        return $this->hasMany(PageComment::class);
    }

    public function announcementComments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
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

    public function supportChatSessions(): HasMany
    {
        return $this->hasMany(SupportChatSession::class);
    }

    public function assignedSupportChatSessions(): HasMany
    {
        return $this->hasMany(SupportChatSession::class, 'assigned_admin_id');
    }

    public function supportChatMessages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class, 'sender_user_id');
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function aiConfigs(): HasMany
    {
        return $this->hasMany(AiUserConfig::class);
    }

    public function aiImageTasks(): HasMany
    {
        return $this->hasMany(AiImageTask::class);
    }

    public function aiChatSessions(): HasMany
    {
        return $this->hasMany(AiChatSession::class);
    }

    public function profileChangeLogs(): HasMany
    {
        return $this->hasMany(UserProfileChangeLog::class);
    }

    public function hasBirthdayToday(): bool
    {
        return $this->birthday
            && $this->birthday->format('m-d') === now()->format('m-d');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getRouteKey(): mixed
    {
        if ($this->exists) {
            $this->ensurePublicId();
        }

        return parent::getRouteKey();
    }

    public function displayName(): string
    {
        return $this->name;
    }

    public function ensureReferralCode(): void
    {
        if (filled($this->referral_code)) {
            return;
        }

        $this->forceFill([
            'referral_code' => static::uniqueReferralCode($this),
        ])->saveQuietly();
    }

    public function referralLink(): string
    {
        $this->ensureReferralCode();

        return rtrim($this->referralBaseUrl(), '/').'/?invite='.rawurlencode((string) $this->referral_code);
    }

    public function isForumModeratorFor(ForumSection $section): bool
    {
        return $this->role === 'admin'
            || $this->moderatedForumSections()->whereKey($section->id)->exists();
    }

    public function isForumPostingBanned(): bool
    {
        return $this->forum_posting_banned_at !== null;
    }

    private static function uniquePublicId(User $user): string
    {
        return AdminAccess::canAccessPanel($user)
            ? static::uniqueStaffPublicId($user)
            : static::uniqueCustomerPublicId($user);
    }

    private static function publicIdForRole(User $user): string
    {
        $publicId = (string) $user->public_id;

        if (AdminAccess::canAccessPanel($user)) {
            if (static::isStaffPublicId($publicId)) {
                return $publicId;
            }

            return static::uniqueStaffPublicId($user);
        }

        if (filled($publicId) && ! static::isStaffPublicId($publicId)) {
            return $publicId;
        }

        return static::uniqueCustomerPublicId($user);
    }

    private static function uniqueStaffPublicId(User $user): string
    {
        $base = $user->email === 'admin@example.com'
            ? self::BACKOFFICE_PUBLIC_ID_PREFIX.'admin'
            : self::BACKOFFICE_PUBLIC_ID_PREFIX.($user->id ?: static::slugForPublicId($user->email ?: Str::random(8)));

        return static::uniquePublicIdFromBase($base, $user);
    }

    private static function uniqueCustomerPublicId(User $user): string
    {
        $base = 'user_'.($user->id ?: Str::lower(Str::random(8)));

        return static::uniquePublicIdFromBase($base, $user);
    }

    private static function uniqueReferralCode(User $user): string
    {
        $base = strtoupper(Str::replace('_', '', (string) ($user->public_id ?: Str::random(8))));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: strtoupper(Str::random(8));
        $base = substr($base, 0, 12);

        return static::uniqueReferralCodeFromBase($base, $user);
    }

    private static function uniqueReferralCodeFromBase(string $base, User $user): string
    {
        $candidate = $base;
        $suffix = 1;

        while (static::query()
            ->where('referral_code', $candidate)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))
            ->exists()) {
            $candidate = substr($base, 0, 10).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function referralBaseUrl(): string
    {
        $request = request();
        $host = trim((string) $request->getHost());

        if ($host === '') {
            $host = trim((string) ($request->server->get('SERVER_NAME') ?: $request->server->get('SERVER_ADDR')));
        }

        if ($host === '') {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        }

        $normalizedHost = strtolower(trim($host, '[]'));

        if (in_array($normalizedHost, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
            $host = 'localhost';
        }

        $scheme = $request->isSecure() ? 'https' : 'http';
        $port = (int) $request->getPort();
        $portSuffix = in_array($port, [0, 80, 443], true) ? '' : ':'.$port;

        return $scheme.'://'.$host.$portSuffix;
    }

    private static function uniquePublicIdFromBase(string $base, User $user): string
    {
        $base = substr(static::slugForPublicId($base), 0, 40);

        $publicId = $base;
        $index = 2;

        while (static::publicIdExists($publicId, $user)) {
            $suffix = '_'.$index++;
            $publicId = substr($base, 0, 40 - strlen($suffix)).$suffix;
        }

        return $publicId;
    }

    private static function publicIdExists(string $publicId, User $user): bool
    {
        $query = static::query()->where('public_id', $publicId);

        if ($user->getKey()) {
            $query->whereKeyNot($user->getKey());
        }

        return $query->exists();
    }

    private static function isStaffPublicId(string $publicId): bool
    {
        return str_starts_with(Str::lower($publicId), self::BACKOFFICE_PUBLIC_ID_PREFIX);
    }

    private static function slugForPublicId(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        return $value === '' ? Str::lower(Str::random(8)) : $value;
    }
}
