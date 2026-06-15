<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUserConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'image_endpoint',
        'image_api_key',
        'chat_endpoint',
        'chat_api_key',
        'image_model',
        'chat_model',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
