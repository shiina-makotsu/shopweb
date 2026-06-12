<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'icon',
        'color',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    public static function fallbackLabels(): array
    {
        return [
            Order::STATUS_PENDING_PAYMENT => '待付款',
            Order::STATUS_PAID => '已付款',
            Order::STATUS_PENDING_SHIPMENT => '待发货',
            Order::STATUS_INCOMING => '进货中',
            Order::STATUS_SHIPPED => '正在运输',
            Order::STATUS_AWAITING_RECEIPT => '待收货',
            Order::STATUS_FULFILLED => '已完成',
            Order::STATUS_CANCELLED => '已取消',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fallbackColors(): array
    {
        return [
            Order::STATUS_PENDING_PAYMENT => 'warning',
            Order::STATUS_PAID => 'success',
            Order::STATUS_PENDING_SHIPMENT => 'info',
            Order::STATUS_INCOMING => 'info',
            Order::STATUS_SHIPPED => 'primary',
            Order::STATUS_AWAITING_RECEIPT => 'warning',
            Order::STATUS_FULFILLED => 'primary',
            Order::STATUS_CANCELLED => 'danger',
        ];
    }

    /**
     * @return array<int, array{code:string,label:string,color:string,sort_order:int}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => Order::STATUS_PENDING_PAYMENT, 'label' => '待付款', 'icon' => 'heroicon-o-banknotes', 'color' => 'warning', 'sort_order' => 10],
            ['code' => Order::STATUS_PAID, 'label' => '已付款', 'icon' => 'heroicon-o-check-circle', 'color' => 'success', 'sort_order' => 20],
            ['code' => Order::STATUS_PENDING_SHIPMENT, 'label' => '待发货', 'icon' => 'heroicon-o-archive-box', 'color' => 'info', 'sort_order' => 30],
            ['code' => Order::STATUS_INCOMING, 'label' => '进货中', 'icon' => 'heroicon-o-arrow-path', 'color' => 'info', 'sort_order' => 40],
            ['code' => Order::STATUS_SHIPPED, 'label' => '正在运输', 'icon' => 'heroicon-o-truck', 'color' => 'primary', 'sort_order' => 50],
            ['code' => Order::STATUS_AWAITING_RECEIPT, 'label' => '待收货', 'icon' => 'heroicon-o-clock', 'color' => 'warning', 'sort_order' => 60],
            ['code' => Order::STATUS_FULFILLED, 'label' => '已完成', 'icon' => 'heroicon-o-check-circle', 'color' => 'success', 'sort_order' => 70],
            ['code' => Order::STATUS_CANCELLED, 'label' => '已取消', 'icon' => 'heroicon-o-x-circle', 'color' => 'danger', 'sort_order' => 80],
        ];
    }

    public static function seedDefaults(): void
    {
        foreach (self::defaults() as $status) {
            self::query()->updateOrCreate(
                ['code' => $status['code']],
                [
                    'label' => $status['label'],
                    'icon' => $status['icon'],
                    'color' => $status['color'],
                    'sort_order' => $status['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
