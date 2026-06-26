<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardCache;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsHealthStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = '运营监控';

    protected function getStats(): array
    {
        $cache = app(AdminDashboardCache::class);
        $orderStats = $cache->orderStats();
        $supportStats = $cache->supportStats();
        $analyticsStats = $cache->analyticsStats();
        $aiStats = $cache->aiStats();
        $systemStats = $cache->systemStats();

        return [
            Stat::make('待确认收款', (string) $orderStats['pending_payment_proofs'])
                ->description('用户已提交付款信息')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($orderStats['pending_payment_proofs'] > 0 ? 'warning' : 'success'),
            Stat::make('待发货/交付', (string) $orderStats['pending_fulfillment'])
                ->description('已付款但仍需处理')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color($orderStats['pending_fulfillment'] > 0 ? 'warning' : 'success'),
            Stat::make('今日成交额', Money::format($orderStats['paid_amount_today']))
                ->description($orderStats['paid_orders_today'].' 个已完成订单')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('客服未读', (string) $supportStats['unread_customer_messages'])
                ->description($supportStats['open_sessions'].' 个进行中会话')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color($supportStats['unread_customer_messages'] > 0 ? 'danger' : 'success'),
            Stat::make('今日访问', (string) $analyticsStats['page_views'])
                ->description($analyticsStats['unique_sessions'].' 个会话')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('gray'),
            Stat::make('商品转化', $analyticsStats['conversion_rate'].'%')
                ->description('加购率 '.$analyticsStats['add_to_cart_rate'].'%')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color($analyticsStats['conversion_rate'] > 0 ? 'success' : 'warning'),
            Stat::make('AI 24h 用量', $this->compactNumber($aiStats['tokens_24h']))
                ->description($aiStats['failed_24h'].' 次失败调用')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color($aiStats['failed_24h'] > 0 ? 'warning' : 'gray'),
            Stat::make('失败队列', (string) $systemStats['failed_jobs'])
                ->description('磁盘剩余 '.$systemStats['disk_free_gb'].' GB')
                ->descriptionIcon(Heroicon::OutlinedServerStack)
                ->color($systemStats['failed_jobs'] > 0 || $systemStats['disk_used_percent'] > 90 ? 'danger' : 'success'),
        ];
    }

    private function compactNumber(int $value): string
    {
        if ($value >= 1000000) {
            return round($value / 1000000, 2).'m';
        }

        if ($value >= 1000) {
            return round($value / 1000, 2).'k';
        }

        return (string) $value;
    }
}
