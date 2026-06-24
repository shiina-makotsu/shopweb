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
        $samples = $metrics->timeline(60);

        return [
            'samples' => $samples,
            'chart' => $this->chart($samples),
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

        return [
            'db_points' => $points('db_ms'),
            'redis_points' => $points('redis_ms'),
            'memory_points' => $points('php_memory_percent'),
            'server_memory_points' => $points('server_memory_used_percent'),
            'cpu_points' => $points('server_cpu_percent'),
            'rpm_points' => $points('requests_per_minute'),
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
