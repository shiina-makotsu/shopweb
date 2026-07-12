<?php

namespace App\Filament\Widgets;

use App\Services\LocalAiResourceGuard;
use Filament\Widgets\Widget;

class LocalAiResourceWidget extends Widget
{
    protected static bool $isLazy = true;

    protected ?string $pollingInterval = '15s';

    protected string $view = 'filament.widgets.local-ai-resource';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $guard = app(LocalAiResourceGuard::class);
        $snapshot = $guard->snapshot();

        return [
            'snapshot' => $snapshot,
            'chart' => $this->chart($guard->timeline(60)),
        ];
    }

    private function chart(array $samples): array
    {
        $width = 1000;
        $height = 220;
        $left = 54;
        $right = 24;
        $top = 16;
        $bottom = 34;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $count = max(1, count($samples) - 1);

        $points = function (string $field, float $max) use ($samples, $left, $top, $plotWidth, $plotHeight, $count): string {
            return collect($samples)
                ->values()
                ->map(function (array $row, int $index) use ($field, $max, $left, $top, $plotWidth, $plotHeight, $count): string {
                    $value = (float) ($row[$field] ?? 0);
                    $x = $left + (($plotWidth / $count) * $index);
                    $y = $top + ($plotHeight - (($value / max(1, $max)) * $plotHeight));

                    return round($x, 2).','.round($y, 2);
                })
                ->implode(' ');
        };

        $maxFree = max(1, ...array_map(fn (array $row): float => (float) ($row['free_mb'] ?? 0), $samples ?: [[]]));

        return [
            'memory_points' => $points('used_percent', 100),
            'free_points' => $points('free_mb', $maxFree),
            'max_free' => round($maxFree),
            'has_data' => count($samples) > 1,
        ];
    }
}
