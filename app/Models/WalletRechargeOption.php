<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletRechargeOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency_code',
        'currency_unit',
        'amount_cents',
        'discount_percent',
        'bonus_cents',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'discount_percent' => 'integer',
            'bonus_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function payableCents(): int
    {
        $discount = $this->discount_percent === null ? 100 : max(0, min(100, (int) $this->discount_percent));

        return (int) round((int) $this->amount_cents * $discount / 100);
    }

    public function creditCents(): int
    {
        return (int) $this->amount_cents + (int) $this->bonus_cents;
    }

    public function displayName(): string
    {
        return $this->name ?: Money::format((int) $this->amount_cents).' 充值';
    }
}
