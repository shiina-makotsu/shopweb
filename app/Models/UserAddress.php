<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone',
        'country',
        'province',
        'city',
        'district',
        'street',
        'detail',
        'raw_text',
        'is_default',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formatted(): string
    {
        $streetDetail = trim(($this->street ?? '').($this->detail ?? ''));

        return trim(implode(' ', array_filter([
            $this->country,
            $this->province,
            $this->city,
            $this->district,
            $streetDetail,
        ])));
    }
}
