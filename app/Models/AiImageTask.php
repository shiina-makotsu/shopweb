<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiImageTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'status',
        'stream',
        'prompt',
        'submitted_prompt',
        'references',
        'config',
        'images',
        'partials',
        'error',
        'elapsed_ms',
        'actual_width',
        'actual_height',
        'meta',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'stream' => 'boolean',
            'references' => 'array',
            'config' => 'array',
            'images' => 'array',
            'partials' => 'array',
            'meta' => 'array',
            'elapsed_ms' => 'integer',
            'actual_width' => 'integer',
            'actual_height' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiImageTask $task): void {
            $task->public_id = $task->public_id ?: 'task-'.Str::uuid()->toString();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toWorkbenchArray(): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status,
            'stream' => (bool) $this->stream,
            'prompt' => (string) $this->prompt,
            'submittedPrompt' => (string) ($this->submitted_prompt ?: $this->prompt),
            'references' => $this->references ?: [],
            'config' => $this->config ?: [],
            'images' => $this->images ?: [],
            'partials' => $this->partials ?: [],
            'error' => (string) $this->error,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'elapsedMs' => (int) $this->elapsed_ms,
            'actualWidth' => $this->actual_width,
            'actualHeight' => $this->actual_height,
            'meta' => $this->meta ?: [],
            'trashed' => $this->deleted_at !== null,
            'deletedAt' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
