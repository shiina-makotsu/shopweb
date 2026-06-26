<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumSectionRead extends Model
{
    protected $fillable = [
        'forum_section_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ForumSection::class, 'forum_section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
