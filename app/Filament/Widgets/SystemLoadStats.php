<?php

namespace App\Filament\Widgets;

use App\Services\SystemLoadMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemLoadStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = '网站负载概览';

    protected function getStats(): array
    {
        $snapshot = app(SystemLoadMetrics::class)->record();

        return [
            Stat::make('MySQL', $snapshot['db_ok'] ? (($snapshot['db_ms'] ?? 0).' ms') : '异常')
                ->description('数据库连通性')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color($snapshot['db_ok'] ? 'success' : 'danger'),
            Stat::make('Redis', $snapshot['redis_ok'] ? (($snapshot['redis_ms'] ?? 0).' ms') : '未连通')
                ->description('缓存/限流通道')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color($snapshot['redis_ok'] ? 'success' : 'warning'),
            Stat::make('PHP 内存', $snapshot['php_memory_mb'].' MB')
                ->description($snapshot['php_memory_percent'] === null ? '未限制' : $snapshot['php_memory_percent'].'%')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color(($snapshot['php_memory_percent'] ?? 0) > 80 ? 'danger' : 'gray'),
            Stat::make('缓存 Store', (string) $snapshot['cache_store'])
                ->description('当前 Laravel 缓存驱动')
                ->descriptionIcon(Heroicon::OutlinedServerStack)
                ->color($snapshot['cache_store'] === 'redis' ? 'success' : 'warning'),
        ];
    }
}
