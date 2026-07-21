<?php

namespace App\Models;

use App\Support\FontAwesome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportContactMethod extends Model
{
    private const ALLOWED_SCHEMES = [
        'http',
        'https',
        'mailto',
        'tel',
        'sms',
        'tg',
        'discord',
        'weixin',
        'mqqapi',
    ];

    protected $fillable = [
        'name',
        'account',
        'url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function linkData(): ?array
    {
        $url = self::safeUrl($this->url);

        if ($url === null) {
            return null;
        }

        return [
            'name' => trim((string) $this->name),
            'account' => trim((string) $this->account),
            'url' => $url,
            'icon' => FontAwesome::normalizeClasses($this->icon) ?: 'fa-solid fa-comments',
        ];
    }

    public static function normalizeLinkData(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $url = self::safeUrl($value['url'] ?? null);
        $name = trim((string) ($value['name'] ?? ''));

        if ($url === null || $name === '') {
            return null;
        }

        return [
            'name' => $name,
            'account' => trim((string) ($value['account'] ?? '')),
            'url' => $url,
            'icon' => FontAwesome::normalizeClasses($value['icon'] ?? null) ?: 'fa-solid fa-comments',
        ];
    }

    public static function safeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $url !== '' && in_array($scheme, self::ALLOWED_SCHEMES, true) ? $url : null;
    }
}
