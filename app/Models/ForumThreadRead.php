<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumThreadRead extends Model
{
    protected $fillable = [
        'forum_thread_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
