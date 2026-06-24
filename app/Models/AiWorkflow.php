<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiWorkflow extends Model
{
    use HasFactory;

    public const TYPE_CHAT = 'chat';
    public const TYPE_IMAGE = 'image';
    public const TYPE_MIXED = 'mixed';

    protected $fillable = [
        'created_by_user_id',
        'name',
        'slug',
        'type',
        'trigger_key',
        'description',
        'nodes',
        'edges',
        'entry_node_id',
        'output_node_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'edges' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $workflow): void {
            if (blank($workflow->slug)) {
                $workflow->slug = Str::slug($workflow->name) ?: 'ai-workflow';
            }

            $workflow->nodes = self::normalizeJsonList($workflow->nodes);
            $workflow->edges = self::normalizeJsonList($workflow->edges);
        });
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_CHAT => '语言模型工作流',
            self::TYPE_IMAGE => '画图模型工作流',
            self::TYPE_MIXED => '混合工作流',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function triggerOptions(): array
    {
        return [
            'support_ai' => '客服 AI',
            'guide_ai' => '导购 AI',
            'ai_chat' => '前台 AI 聊天',
            'ai_image' => '前台 AI 生图',
            'manual' => '手动调用',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<int, mixed>
     */
    private static function normalizeJsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }
}
