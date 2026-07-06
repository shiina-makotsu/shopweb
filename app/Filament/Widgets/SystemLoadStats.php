<?php

namespace App\Filament\Widgets;

use App\Services\SystemLoadMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemLoadStats extends StatsOverviewWidget
{
    protected static bool $isLazy = true;

    protected ?string $pollingInterval = '15s';

    protected ?string $heading = '网站负载概览';

    protected function getStats(): array
    {
        $snapshot = app(SystemLoadMetrics::class)->latestCachedSnapshot();

        return [
            Stat::make('服务器 CPU', $snapshot['server_cpu_percent'] === null ? '未知' : $snapshot['server_cpu_percent'].'%')
                ->description('来源：'.$snapshot['server_cpu_source'].' / '.$snapshot['cpu_cores'].' 核')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color(($snapshot['server_cpu_percent'] ?? 0) > 90 ? 'danger' : 'success'),
            Stat::make('服务器内存', $snapshot['server_memory_used_percent'] === null ? '未知' : $snapshot['server_memory_used_percent'].'%')
                ->description(($snapshot['server_memory_free_mb'] ?? '-').' MB 可用 / 来源：'.$snapshot['server_memory_source'])
                ->descriptionIcon(Heroicon::OutlinedServerStack)
                ->color(($snapshot['server_memory_used_percent'] ?? 0) > 90 ? 'danger' : 'success'),
            Stat::make('MySQL', $snapshot['db_ok'] ? (($snapshot['db_ms'] ?? 0).' ms') : '异常')
                ->description('数据库连通性')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color($snapshot['db_ok'] ? 'success' : 'danger'),
            Stat::make('Redis', $snapshot['redis_ok'] ? (($snapshot['redis_ms'] ?? 0).' ms') : '未连通')
                ->description('缓存 / 限流通道')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->color($snapshot['redis_ok'] ? 'success' : 'warning'),
            Stat::make('请求/分钟', (string) $snapshot['requests_per_minute'])
                ->description('前台 '.$snapshot['frontend_requests_per_minute'].' / 后台 '.$snapshot['admin_requests_per_minute'])
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color(($snapshot['requests_per_minute'] ?? 0) > 600 ? 'danger' : (($snapshot['requests_per_minute'] ?? 0) > 300 ? 'warning' : 'gray')),
            Stat::make('存储空间', $snapshot['storage_free_gb'].' GB')
                ->description('已使用 '.$snapshot['storage_used_percent'].'%')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color(($snapshot['storage_used_percent'] ?? 0) > 90 ? 'danger' : 'success'),
            Stat::make('PHP 内存', $snapshot['php_memory_mb'].' MB')
                ->description($snapshot['php_memory_percent'] === null ? '未限制' : $snapshot['php_memory_percent'].'% / 峰值 '.$snapshot['php_peak_memory_mb'].' MB')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color(($snapshot['php_memory_percent'] ?? 0) > 80 ? 'danger' : 'gray'),
            Stat::make('队列连接', (string) $snapshot['queue_connection'])
                ->description('后台任务通道')
                ->descriptionIcon(Heroicon::OutlinedQueueList)
                ->color($snapshot['queue_connection'] === 'sync' ? 'warning' : 'success'),
        ];
    }
}
