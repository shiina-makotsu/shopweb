<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'title',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiChatSession $session): void {
            $session->public_id = $session->public_id ?: 'chat-'.Str::uuid()->toString();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class);
    }

    public function toWorkbenchArray(): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title ?: '新会话',
            'messages' => $this->messages
                ->sortBy('created_at')
                ->map(fn (AiChatMessage $message): array => $message->toWorkbenchArray())
                ->values()
                ->all(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'trashed' => $this->deleted_at !== null,
            'deletedAt' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
