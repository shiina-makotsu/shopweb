<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\RegexSearch;
use Illuminate\Support\Collection;

class SupportQuickReply extends Model
{
    public const TRIGGER_MESSAGE = 'message';
    public const TRIGGER_SESSION_ENTRY = 'session_entry';

    public const MATCH_KEYWORD = 'keyword';
    public const MATCH_REGEX = 'regex';

    public const ACTION_REPLY = 'reply';
    public const ACTION_AI = 'ai';
    public const ACTION_NOTIFY_STAFF = 'notify_staff';

    protected $fillable = [
        'title',
        'body',
        'contact_method_ids',
        'category',
        'trigger_event',
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
            'contact_method_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SupportQuickReply $reply): void {
            if ($reply->trigger_event === self::TRIGGER_SESSION_ENTRY) {
                $reply->trigger_action = self::ACTION_REPLY;
                $reply->match_pattern = null;
                $reply->match_mode = self::MATCH_KEYWORD;
            }

            if (! in_array($reply->trigger_event, [self::TRIGGER_MESSAGE, self::TRIGGER_SESSION_ENTRY], true)) {
                $reply->trigger_event = self::TRIGGER_MESSAGE;
            }

            $reply->contact_method_ids = collect($reply->contact_method_ids)
                ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    public function matches(string $message): bool
    {
        if ($this->trigger_event !== self::TRIGGER_MESSAGE) {
            return false;
        }

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

    public function triggerEventLabel(): string
    {
        return $this->trigger_event === self::TRIGGER_SESSION_ENTRY ? '进入会话' : '消息命中';
    }

    /** @return Collection<int, SupportContactMethod> */
    public function contactMethods(): Collection
    {
        $ids = collect($this->contact_method_ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $positions = $ids->flip();

        return SupportContactMethod::query()
            ->active()
            ->whereKey($ids->all())
            ->get()
            ->sortBy(fn (SupportContactMethod $method): int => (int) $positions->get($method->id, PHP_INT_MAX))
            ->values();
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
