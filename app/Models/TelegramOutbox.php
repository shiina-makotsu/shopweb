<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramOutbox extends Model
{
    protected $table = 'telegram_outbox';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
