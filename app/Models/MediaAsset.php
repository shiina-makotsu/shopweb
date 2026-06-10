<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                $asset->name = self::nameFromPath((string) $asset->path);
            }

            if (self::isExternalUrl($asset->path)) {
                $asset->disk = 'external';
                $asset->mime_type = $asset->mime_type ?: 'image/external';
                $asset->size = null;

                return;
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
        if (self::isExternalUrl($this->path)) {
            return $this->path;
        }

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
        return Str::startsWith($this->mime_type ?? '', 'image/') || (self::isExternalUrl($this->path) && self::looksLikeImageUrl($this->path));
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
        if (self::isExternalUrl($this->path)) {
            return '外部链接';
        }

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
     * @param  array<string, mixed>  $data
     */
    public static function createImageFromUploadOrUrl(array $data, string $usage, string $defaultDisk = 'public_uploads'): self
    {
        $path = $data['path'] ?? null;
        $path = is_array($path) ? reset($path) : $path;

        if (! is_string($path) || blank($path)) {
            $path = self::pathFromUploadOrUrl($data);
        }

        $isExternal = self::isExternalUrl($path);

        return self::query()->create([
            'name' => $data['name'] ?? self::nameFromPath($path),
            'path' => $path,
            'disk' => $isExternal ? 'external' : $defaultDisk,
            'mime_type' => $isExternal ? 'image/external' : ($data['mime_type'] ?? null),
            'alt' => $data['alt'] ?? null,
            'usage' => $usage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function pathFromUploadOrUrl(array $data): string
    {
        $externalUrl = trim((string) ($data['external_url'] ?? ''));

        if ($externalUrl !== '') {
            if (! self::isExternalUrl($externalUrl)) {
                throw ValidationException::withMessages([
                    'external_url' => '请输入以 http:// 或 https:// 开头的图片链接。',
                ]);
            }

            return $externalUrl;
        }

        $path = $data['path'] ?? null;
        $path = is_array($path) ? reset($path) : $path;

        if (! is_string($path) || blank($path)) {
            throw ValidationException::withMessages([
                'path' => '请上传文件，或填写外部图片链接。',
            ]);
        }

        return $path;
    }

    public static function isExternalUrl(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        $scheme = parse_url($path, PHP_URL_SCHEME);

        return in_array(Str::lower((string) $scheme), ['http', 'https'], true)
            && filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    public static function nameFromPath(string $path): string
    {
        if (self::isExternalUrl($path)) {
            $urlPath = (string) parse_url($path, PHP_URL_PATH);
            $name = pathinfo($urlPath, PATHINFO_FILENAME);

            return $name ?: ((string) parse_url($path, PHP_URL_HOST) ?: 'external-image');
        }

        return pathinfo($path, PATHINFO_FILENAME) ?: basename($path);
    }

    public static function looksLikeImageUrl(?string $path): bool
    {
        if (! self::isExternalUrl($path)) {
            return false;
        }

        $extension = Str::lower(pathinfo((string) parse_url((string) $path, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], true);
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
