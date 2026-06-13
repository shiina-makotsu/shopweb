<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'quoted_message_id',
        'body',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime_type, 'image/');
    }
}
