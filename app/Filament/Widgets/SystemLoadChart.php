<?php

namespace App\Filament\Widgets;

use App\Services\SystemLoadMetrics;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class SystemLoadChart extends Widget
{
    protected static bool $isLazy = true;

    protected ?string $pollingInterval = '15s';

    protected string $view = 'filament.widgets.system-load-chart';

    protected int | string | array $columnSpan = 'full';

    public ?string $rangeStart = null;

    public ?string $rangeEnd = null;

    public int $visiblePointInterval = 1;

    protected function getViewData(): array
    {
        $metrics = app(SystemLoadMetrics::class);

        $rangeEnd = $this->parseRangeDate($this->rangeEnd)?->second(0) ?? now()->second(0);
        $rangeStart = $this->parseRangeDate($this->rangeStart)?->second(0) ?? $rangeEnd->copy()->subHours(24)->addMinute();

        if ($rangeStart->greaterThan($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $interval = $this->normalizedVisiblePointInterval();
        $samples = $metrics->timelineBetween($rangeStart, $rangeEnd);

        return [
            'charts' => $this->splitCharts($samples, $interval),
            'rangeStart' => $rangeStart->format('Y-m-d\TH:i'),
            'rangeEnd' => $rangeEnd->format('Y-m-d\TH:i'),
            'visiblePointInterval' => $interval,
            'visiblePointIntervalOptions' => [1, 5, 10, 15, 30, 60],
        ];
    }

    public function applyFilters(): void
    {
        $this->visiblePointInterval = $this->normalizedVisiblePointInterval();
    }

    public function resetFilters(): void
    {
        $this->rangeStart = null;
        $this->rangeEnd = null;
        $this->visiblePointInterval = 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{title:string,chart:array}>
     */
    private function splitCharts(array $samples, int $visiblePointInterval): array
    {
        if (count($samples) <= 720) {
            return [[
                'title' => '负载走势',
                'chart' => $this->chart($samples, $visiblePointInterval),
            ]];
        }

        return [
            [
                'title' => '前 12 小时负载',
                'chart' => $this->chart(array_slice($samples, 0, 720), $visiblePointInterval),
            ],
            [
                'title' => '后 12 小时负载',
                'chart' => $this->chart(array_slice($samples, 720), $visiblePointInterval),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<string, mixed>
     */
    private function chart(array $samples, int $visiblePointInterval = 1): array
    {
        $width = 1000;
        $height = 260;
        $left = 58;
        $right = 28;
        $top = 18;
        $bottom = 40;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $count = max(1, count($samples) - 1);
        $max = max(1, ...array_map(fn (array $row): float => max(
            (float) ($row['db_ms'] ?? 0),
            (float) ($row['redis_ms'] ?? 0),
            (float) ($row['php_memory_percent'] ?? 0),
            (float) ($row['server_memory_used_percent'] ?? 0),
            (float) ($row['server_cpu_percent'] ?? 0),
            (float) ($row['requests_per_minute'] ?? 0),
        ), $samples ?: [[]]));

        $points = function (string $field) use ($samples, $left, $top, $plotWidth, $plotHeight, $count, $max): string {
            return collect($samples)
                ->values()
                ->map(function (array $row, int $index) use ($field, $left, $top, $plotWidth, $plotHeight, $count, $max): string {
                    $value = (float) ($row[$field] ?? 0);
                    $x = $left + (($plotWidth / $count) * $index);
                    $y = $top + ($plotHeight - (($value / $max) * $plotHeight));

                    return round($x, 2).','.round($y, 2);
                })
                ->implode(' ');
        };

        $samplePoints = collect($samples)
            ->values()
            ->map(function (array $row, int $index) use ($left, $top, $plotWidth, $plotHeight, $count, $max): array {
                $x = round($left + (($plotWidth / $count) * $index), 2);
                $valueY = static fn (float | int | null $value): float => round($top + ($plotHeight - (((float) $value / $max) * $plotHeight)), 2);

                return [
                    'index' => $index,
                    'x' => $x,
                    'time' => (string) ($row['time'] ?? ''),
                    'db_ms' => $row['db_ms'] ?? 0,
                    'redis_ms' => $row['redis_ms'] ?? 0,
                    'php_memory_percent' => $row['php_memory_percent'] ?? 0,
                    'server_memory_used_percent' => $row['server_memory_used_percent'] ?? 0,
                    'server_cpu_percent' => $row['server_cpu_percent'] ?? 0,
                    'requests_per_minute' => $row['requests_per_minute'] ?? 0,
                    'db_y' => $valueY($row['db_ms'] ?? 0),
                    'redis_y' => $valueY($row['redis_ms'] ?? 0),
                    'memory_y' => $valueY($row['php_memory_percent'] ?? 0),
                    'server_memory_y' => $valueY($row['server_memory_used_percent'] ?? 0),
                    'cpu_y' => $valueY($row['server_cpu_percent'] ?? 0),
                    'rpm_y' => $valueY($row['requests_per_minute'] ?? 0),
                ];
            })
            ->all();

        $series = [
            ['name' => 'MySQL ms', 'color' => '#3b82f6', 'points' => $points('db_ms'), 'width' => 2],
            ['name' => 'Redis ms', 'color' => '#ec4899', 'points' => $points('redis_ms'), 'width' => 2],
            ['name' => 'PHP 内存 %', 'color' => '#8b5cf6', 'points' => $points('php_memory_percent'), 'width' => 2],
            ['name' => '服务器内存 %', 'color' => '#f59e0b', 'points' => $points('server_memory_used_percent'), 'width' => 2],
            ['name' => '服务器 CPU %', 'color' => '#ef4444', 'points' => $points('server_cpu_percent'), 'width' => 2],
            ['name' => '请求/分钟', 'color' => '#22c55e', 'points' => $points('requests_per_minute'), 'width' => 2],
        ];

        $markerPoints = collect($samplePoints)
            ->filter(fn (array $point): bool => $point['index'] === 0 || $point['index'] === count($samplePoints) - 1 || $point['index'] % $visiblePointInterval === 0)
            ->values();

        $markers = $markerPoints
            ->flatMap(fn (array $point): array => [
                $this->marker($point, 'db_y', 'MySQL ms', 'MySQL：'.$point['db_ms'].' ms', '#3b82f6'),
                $this->marker($point, 'redis_y', 'Redis ms', 'Redis：'.$point['redis_ms'].' ms', '#ec4899'),
                $this->marker($point, 'memory_y', 'PHP 内存 %', 'PHP 内存：'.$point['php_memory_percent'].'%', '#8b5cf6'),
                $this->marker($point, 'server_memory_y', '服务器内存 %', '服务器内存：'.$point['server_memory_used_percent'].'%', '#f59e0b'),
                $this->marker($point, 'cpu_y', '服务器 CPU %', '服务器 CPU：'.$point['server_cpu_percent'].'%', '#ef4444'),
                $this->marker($point, 'rpm_y', '请求/分钟', '请求/分钟：'.$point['requests_per_minute'], '#22c55e'),
            ])
            ->all();

        return [
            'series' => $series,
            'markers' => $markers,
            'y_labels' => collect(range(0, 4))
                ->map(fn (int $step): string => (string) round(($max / 4) * (4 - $step), 1))
                ->all(),
            'x_labels' => collect($samples)
                ->values()
                ->filter(fn (array $row, int $index): bool => $index === 0 || $index === count($samples) - 1 || $index % 60 === 0)
                ->map(fn (array $row, int $index): array => [
                    'label' => (string) ($row['time'] ?? ''),
                    'x' => round($left + (($plotWidth / $count) * $index), 2),
                ])
                ->values()
                ->all(),
            'has_data' => count($samples) > 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array<string, mixed>
     */
    private function marker(array $point, string $yKey, string $name, string $value, string $color): array
    {
        return [
            'x' => $point['x'],
            'y' => $point[$yKey],
            'title' => $point['time'],
            'line_1' => $name,
            'line_2' => $value,
            'color' => $color,
        ];
    }

    private function normalizedVisiblePointInterval(): int
    {
        $interval = (int) $this->visiblePointInterval;

        return in_array($interval, [1, 5, 10, 15, 30, 60], true) ? $interval : 1;
    }

    private function parseRangeDate(?string $value): ?CarbonInterface
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
