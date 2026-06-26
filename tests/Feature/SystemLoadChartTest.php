<?php

namespace Tests\Feature;

use App\Filament\Widgets\SystemLoadChart;
use App\Services\SystemLoadMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class SystemLoadChartTest extends TestCase
{
    public function test_system_load_timeline_keeps_one_point_per_minute_with_sparse_samples(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        Cache::put('shop:system-load:samples', [
            [
                'minute_key' => '2026-06-26 11:56',
                'timestamp' => Carbon::parse('2026-06-26 11:56:12')->timestamp,
                'time' => '11:56:12',
                'db_ms' => 5,
                'redis_ms' => 2,
                'php_memory_percent' => 10,
                'server_memory_used_percent' => 31,
                'server_cpu_percent' => 7,
                'requests_per_minute' => 3,
            ],
            [
                'minute_key' => '2026-06-26 12:00',
                'timestamp' => Carbon::parse('2026-06-26 12:00:08')->timestamp,
                'time' => '12:00:08',
                'db_ms' => 8,
                'redis_ms' => 3,
                'php_memory_percent' => 12,
                'server_memory_used_percent' => 35,
                'server_cpu_percent' => 11,
                'requests_per_minute' => 9,
            ],
        ], 90000);

        $timeline = app(SystemLoadMetrics::class)->timeline(1440);

        $this->assertCount(1440, $timeline);
        $this->assertSame('12:00', $timeline[1439]['time']);
        $this->assertSame('11:59', $timeline[1438]['time']);
        $this->assertSame(8, $timeline[1439]['db_ms']);
        $this->assertSame(5, $timeline[1438]['db_ms']);
    }

    public function test_system_load_timeline_can_read_a_specific_minute_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        Cache::put('shop:system-load:samples', [
            [
                'minute_key' => '2026-06-26 09:02',
                'timestamp' => Carbon::parse('2026-06-26 09:02:12')->timestamp,
                'db_ms' => 15,
                'redis_ms' => 2,
                'php_memory_percent' => 10,
                'server_memory_used_percent' => 31,
                'server_cpu_percent' => 7,
                'requests_per_minute' => 3,
            ],
        ], 90000);

        $timeline = app(SystemLoadMetrics::class)->timelineBetween(
            Carbon::parse('2026-06-26 09:00:00'),
            Carbon::parse('2026-06-26 09:05:00'),
        );

        $this->assertCount(6, $timeline);
        $this->assertSame('09:00', $timeline[0]['time']);
        $this->assertSame('09:02', $timeline[2]['time']);
        $this->assertSame(15, $timeline[2]['db_ms']);
        $this->assertSame(15, $timeline[3]['db_ms']);
    }

    public function test_system_load_chart_keeps_minute_markers_but_sparse_axis_labels(): void
    {
        $widget = app(SystemLoadChart::class);
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('chart');
        $method->setAccessible(true);

        $samples = collect(range(0, 719))
            ->map(fn (int $index): array => [
                'time' => Carbon::parse('2026-06-26 00:00:00')->addMinutes($index)->format('H:i'),
                'db_ms' => $index % 5,
                'redis_ms' => $index % 3,
                'php_memory_percent' => 10,
                'server_memory_used_percent' => 35,
                'server_cpu_percent' => 12,
                'requests_per_minute' => $index % 7,
            ])
            ->all();

        $chart = $method->invoke($widget, $samples, 1);

        $this->assertCount(720 * 6, $chart['markers']);
        $this->assertLessThan(20, count($chart['x_labels']));
        $this->assertSame('00:00', $chart['x_labels'][0]['label']);
        $this->assertSame('11:59', $chart['x_labels'][array_key_last($chart['x_labels'])]['label']);
    }

    public function test_system_load_chart_can_reduce_visible_markers_without_downsampling_lines(): void
    {
        $widget = app(SystemLoadChart::class);
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('chart');
        $method->setAccessible(true);

        $samples = collect(range(0, 719))
            ->map(fn (int $index): array => [
                'time' => Carbon::parse('2026-06-26 00:00:00')->addMinutes($index)->format('H:i'),
                'db_ms' => $index % 5,
                'redis_ms' => $index % 3,
                'php_memory_percent' => 10,
                'server_memory_used_percent' => 35,
                'server_cpu_percent' => 12,
                'requests_per_minute' => $index % 7,
            ])
            ->all();

        $chart = $method->invoke($widget, $samples, 15);
        $linePointCount = substr_count($chart['series'][0]['points'], ' ') + 1;

        $this->assertSame(720, $linePointCount);
        $this->assertLessThan(720 * 6, count($chart['markers']));
        $this->assertGreaterThanOrEqual(49 * 6, count($chart['markers']));
    }
}
