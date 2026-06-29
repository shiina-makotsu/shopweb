<?php

use App\Models\SystemMetricSnapshot;
use App\Services\SystemLoadMetrics;

it('persists system load snapshots for historical dashboard ranges', function (): void {
    $metrics = app(SystemLoadMetrics::class);

    $snapshot = $metrics->record();

    expect(SystemMetricSnapshot::query()->count())->toBe(1)
        ->and($snapshot['minute_key'])->toBe(now()->format('Y-m-d H:i'));

    $timeline = $metrics->timelineBetween(now()->subMinutes(2), now());

    expect($timeline)->toHaveCount(3)
        ->and(collect($timeline)->where('sampled', true)->count())->toBeGreaterThanOrEqual(1);
});

it('keeps recent metric history while pruning data beyond the retention window', function (): void {
    config(['shop.server_monitor.retention_days' => 62]);

    SystemMetricSnapshot::query()->create([
        'sampled_at' => now()->subDays(70)->startOfMinute(),
        'db_ok' => true,
        'redis_ok' => false,
    ]);

    SystemMetricSnapshot::query()->create([
        'sampled_at' => now()->subDays(30)->startOfMinute(),
        'db_ok' => true,
        'redis_ok' => false,
    ]);

    app(SystemLoadMetrics::class)->record();

    expect(SystemMetricSnapshot::query()->where('sampled_at', '<', now()->subDays(62))->count())->toBe(0)
        ->and(SystemMetricSnapshot::query()->where('sampled_at', '>=', now()->subDays(62))->count())->toBeGreaterThanOrEqual(2);
});

it('records a persistent snapshot from request tracking at most once per minute', function (): void {
    $metrics = app(SystemLoadMetrics::class);

    $metrics->recordRequest('frontend');
    $metrics->recordRequest('admin');

    expect(SystemMetricSnapshot::query()->count())->toBe(1);
});
