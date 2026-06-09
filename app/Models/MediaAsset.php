<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use HasFactory;

    public const USAGE_GENERAL = 'general';
    public const USAGE_LOGO = 'logo';
    public const USAGE_HOME = 'home';
    public const USAGE_PAGE = 'page';
    public const USAGE_PRODUCT = 'product';
    public const USAGE_PRESENTATION = 'presentation';
    public const USAGE_BACKGROUND = 'background';
    public const USAGE_FORUM = 'forum';
    public const LIBRARY_SITE = 'site';
    public const LIBRARY_FORUM_USER = 'forum_user';

    protected $fillable = [
        'name',
        'path',
        'disk',
        'mime_type',
        'size',
        'alt',
        'usage',
        'library',
        'uploaded_by_id',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (MediaAsset $asset): void {
            $asset->disk = $asset->disk ?: 'public_uploads';
            $asset->usage = $asset->usage ?: self::USAGE_GENERAL;
            $asset->library = $asset->library ?: self::LIBRARY_SITE;

            if (! $asset->name) {
                $asset->name = pathinfo($asset->path, PATHINFO_FILENAME) ?: basename($asset->path);
            }

            $storage = Storage::disk($asset->disk);

            if ($asset->path && $storage->exists($asset->path)) {
                $asset->mime_type = $asset->mime_type ?: $storage->mimeType($asset->path);
                $asset->size = $asset->size ?: $storage->size($asset->path);
            }
        });
    }

    public function url(): string
    {
        return Storage::disk($this->disk ?: 'public_uploads')->url($this->path);
    }

    public function pagesUsingAsCover(): HasMany
    {
        return $this->hasMany(Page::class, 'cover_media_asset_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function scopeUnused(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('pagesUsingAsCover')
            ->whereNotIn('path', self::settingAssetPaths());
    }

    public function scopeMediaOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('mime_type', 'like', 'image/%')
                ->orWhere('mime_type', 'like', 'video/%');
        });
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'image/');
    }

    public function isVideo(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'video/');
    }

    public function isReferenced(): bool
    {
        return $this->pagesUsingAsCover()->exists()
            || in_array($this->path, self::settingAssetPaths(), true)
            || $this->markdownReferenceCount() > 0;
    }

    public function usageSummary(): string
    {
        $labels = [];

        $coverCount = $this->pagesUsingAsCover()->count();
        if ($coverCount > 0) {
            $labels[] = "页面封面 {$coverCount}";
        }

        $settingLabels = [
            'logo_path' => '站点 Logo',
            'favicon_path' => '站点图标',
            'home_welcome_image_path' => '首页欢迎图',
            'home_background_path' => '首页背景',
            'auth_background_path' => '登录背景',
        ];

        foreach ($settingLabels as $field => $label) {
            if (SiteSetting::query()->where($field, $this->path)->exists()) {
                $labels[] = $label;
            }
        }

        $markdownCount = $this->markdownReferenceCount();
        if ($markdownCount > 0) {
            $labels[] = "Markdown 引用 {$markdownCount}";
        }

        return $labels ? implode(' / ', $labels) : '未使用';
    }

    public function markdownReferenceCount(): int
    {
        if (! $this->path) {
            return 0;
        }

        $url = $this->url();

        $pageCount = Page::query()
            ->where(function (Builder $query) use ($url): void {
                $query
                    ->where('body', 'like', "%{$this->path}%")
                    ->orWhere('body', 'like', "%{$url}%");
            })
            ->count();

        $settingCount = SiteSetting::query()
            ->where(function (Builder $query) use ($url): void {
                $query
                    ->where('home_content', 'like', "%{$this->path}%")
                    ->orWhere('home_content', 'like', "%{$url}%")
                    ->orWhere('payment_instructions', 'like', "%{$this->path}%")
                    ->orWhere('payment_instructions', 'like', "%{$url}%")
                    ->orWhere('contact_info', 'like', "%{$this->path}%")
                    ->orWhere('contact_info', 'like', "%{$url}%");
            })
            ->count();

        return $pageCount + $settingCount;
    }

    public function sizeForHumans(): string
    {
        $bytes = (int) ($this->size ?? 0);

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    /**
     * @return array<string, string>
     */
    public static function usageOptions(): array
    {
        return [
            self::USAGE_GENERAL => '通用资源',
            self::USAGE_LOGO => 'Logo/站点图标',
            self::USAGE_HOME => '首页内容',
            self::USAGE_PAGE => '自定义页面',
            self::USAGE_PRODUCT => '商品图片',
            self::USAGE_PRESENTATION => 'PPT/展示资料',
            self::USAGE_BACKGROUND => '背景图',
            self::USAGE_FORUM => '论坛用户资源',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function libraryOptions(): array
    {
        return [
            self::LIBRARY_SITE => '网站资源文件',
            self::LIBRARY_FORUM_USER => '论坛用户资源文件',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function settingAssetPaths(): array
    {
        return SiteSetting::query()
            ->get(['logo_path', 'favicon_path', 'home_welcome_image_path', 'home_background_path', 'auth_background_path'])
            ->flatMap(fn (SiteSetting $setting): array => [
                $setting->logo_path,
                $setting->favicon_path,
                $setting->home_welcome_image_path,
                $setting->home_background_path,
                $setting->auth_background_path,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
