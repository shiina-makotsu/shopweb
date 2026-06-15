<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSaleCampaign extends Model
{
    use HasFactory;

    public const TYPE_ONCE = 'once';
    public const TYPE_DAILY = 'daily';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_YEARLY = 'yearly';

    protected $fillable = [
        'name',
        'schedule_type',
        'starts_on',
        'ends_on',
        'starts_at_time',
        'ends_at_time',
        'month_days',
        'week_days',
        'year_dates',
        'generate_days_ahead',
        'last_generated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'month_days' => 'array',
            'week_days' => 'array',
            'year_dates' => 'array',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleCampaignItem::class);
    }

    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    public static function scheduleTypeOptions(): array
    {
        return [
            self::TYPE_ONCE => '一次秒杀',
            self::TYPE_YEARLY => '每年一次',
            self::TYPE_MONTHLY => '每月一次',
            self::TYPE_WEEKLY => '每周一次',
            self::TYPE_DAILY => '每天一次',
        ];
    }
}
