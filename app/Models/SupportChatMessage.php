<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatMessage extends Model
{
    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_GUEST = 'guest';
    public const SENDER_ADMIN = 'admin';
    public const SENDER_SYSTEM = 'system';

    protected $fillable = [
        'support_chat_session_id',
        'quoted_message_id',
        'support_quick_reply_id',
        'sender_user_id',
        'sender_type',
        'body',
        'contact_links',
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
            'contact_links' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportChatSession::class, 'support_chat_session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
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
