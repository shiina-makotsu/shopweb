<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_chat_session_id',
        'user_id',
        'role',
        'content',
        'files',
        'model',
        'reasoning_mode',
        'reasoning_label',
        'is_error',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'is_error' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'ai_chat_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toWorkbenchArray(): array
    {
        return [
            'role' => $this->role,
            'content' => (string) $this->content,
            'files' => $this->files ?: [],
            'model' => (string) $this->model,
            'reasoning' => (string) $this->reasoning_mode,
            'reasoningLabel' => (string) $this->reasoning_label,
            'error' => (bool) $this->is_error,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
