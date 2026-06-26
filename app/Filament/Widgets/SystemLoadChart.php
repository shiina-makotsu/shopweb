<?php

namespace App\Filament\Widgets;

use App\Services\SystemLoadMetrics;
use Filament\Widgets\Widget;

class SystemLoadChart extends Widget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '15s';

    protected string $view = 'filament.widgets.system-load-chart';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $metrics = app(SystemLoadMetrics::class);
        $metrics->record();
        $samples = $metrics->timeline(1440);

        return [
            'samples' => $samples,
            'charts' => $this->splitCharts($samples),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{title:string,chart:array}>
     */
    private function splitCharts(array $samples): array
    {
        if (count($samples) <= 720) {
            return [[
                'title' => '最近负载',
                'chart' => $this->chart($samples),
            ]];
        }

        return [
            [
                'title' => '前 12 小时负载',
                'chart' => $this->chart(array_slice($samples, 0, -720)),
            ],
            [
                'title' => '后 12 小时负载',
                'chart' => $this->chart(array_slice($samples, -720)),
            ],
        ];
    }

    private function chart(array $samples): array
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
                $valueY = static fn (float|int $value) => round($top + ($plotHeight - (((float) $value / $max) * $plotHeight)), 2);

                return [
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

        $markers = collect($samplePoints)
            ->flatMap(fn (array $point): array => [
                [
                    'x' => $point['x'],
                    'y' => $point['db_y'],
                    'title' => $point['time'],
                    'line_1' => 'MySQL ms',
                    'line_2' => 'MySQL：'.$point['db_ms'].' ms',
                    'color' => '#3b82f6',
                ],
                [
                    'x' => $point['x'],
                    'y' => $point['redis_y'],
                    'title' => $point['time'],
                    'line_1' => 'Redis ms',
                    'line_2' => 'Redis：'.$point['redis_ms'].' ms',
                    'color' => '#ec4899',
                ],
                [
                    'x' => $point['x'],
                    'y' => $point['memory_y'],
                    'title' => $point['time'],
                    'line_1' => 'PHP 内存 %',
                    'line_2' => 'PHP 内存：'.$point['php_memory_percent'].'%',
                    'color' => '#8b5cf6',
                ],
                [
                    'x' => $point['x'],
                    'y' => $point['server_memory_y'],
                    'title' => $point['time'],
                    'line_1' => '服务器内存 %',
                    'line_2' => '服务器内存：'.$point['server_memory_used_percent'].'%',
                    'color' => '#f59e0b',
                ],
                [
                    'x' => $point['x'],
                    'y' => $point['cpu_y'],
                    'title' => $point['time'],
                    'line_1' => '服务器 CPU %',
                    'line_2' => '服务器 CPU：'.$point['server_cpu_percent'].'%',
                    'color' => '#ef4444',
                ],
                [
                    'x' => $point['x'],
                    'y' => $point['rpm_y'],
                    'title' => $point['time'],
                    'line_1' => '请求/分钟',
                    'line_2' => '请求/分钟：'.$point['requests_per_minute'],
                    'color' => '#22c55e',
                ],
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
                ->filter(fn (array $row, int $index): bool => $index === 0 || $index === count($samples) - 1 || $index % 12 === 0)
                ->map(fn (array $row, int $index): array => [
                    'label' => (string) ($row['time'] ?? ''),
                    'x' => round($left + (($plotWidth / $count) * $index), 2),
                ])
                ->values()
                ->all(),
            'has_data' => count($samples) > 1,
        ];
    }
}
