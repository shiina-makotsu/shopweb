<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\RegexSearch;

class SupportQuickReply extends Model
{
    public const MATCH_KEYWORD = 'keyword';
    public const MATCH_REGEX = 'regex';

    public const ACTION_REPLY = 'reply';
    public const ACTION_AI = 'ai';
    public const ACTION_NOTIFY_STAFF = 'notify_staff';

    protected $fillable = [
        'title',
        'body',
        'category',
        'match_pattern',
        'match_mode',
        'trigger_action',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function matches(string $message): bool
    {
        $pattern = trim((string) $this->match_pattern);

        if ($pattern === '') {
            return false;
        }

        if ($this->match_mode === self::MATCH_REGEX) {
            $regex = $this->compileRegex($pattern);

            return $regex ? preg_match($regex, $message) === 1 : false;
        }

        $needles = preg_split('/[\r\n,，]+/u', $pattern) ?: [];

        foreach ($needles as $needle) {
            $needle = trim($needle);

            if ($needle !== '' && mb_stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public function matchModeLabel(): string
    {
        return $this->match_mode === self::MATCH_REGEX ? '正则' : '关键词';
    }

    public function triggerActionLabel(): string
    {
        return match ($this->trigger_action) {
            self::ACTION_AI => 'AI 接待',
            self::ACTION_NOTIFY_STAFF => '提醒客服',
            default => '自动回复',
        };
    }

    private function compileRegex(string $pattern): ?string
    {
        $normalized = RegexSearch::patternFrom($pattern) ?: trim($pattern);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace('~', '\~', $normalized);

        return @preg_match("~{$normalized}~u", '') === false ? null : "~{$normalized}~u";
    }
}
