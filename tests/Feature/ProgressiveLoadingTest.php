<?php

use App\Filament\Widgets\AiChannelHealthWidget;
use App\Filament\Widgets\LocalAiResourceWidget;
use App\Filament\Widgets\LowStockVariants;
use App\Filament\Widgets\OperationsHealthStats;
use App\Filament\Widgets\SystemLoadChart;
use App\Filament\Widgets\SystemLoadStats;
use App\Filament\Widgets\VisitSourceOverview;

it('waits for the storefront page before progressively prefetching navigation', function (): void {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain('shopweb:storefront-prefetch:')
        ->toContain("'X-ShopWeb-Purpose': 'storefront-prefetch'")
        ->toContain("window.addEventListener('load', markReady")
        ->toContain("window.requestIdleCallback(start")
        ->toContain("['slow-2g', '2g']")
        ->toContain('runtime.active || runtime.queue.length === 0');
});

it('does not let a guest prefetch replace the intended login destination', function (): void {
    config(['shop.first_visit_loading.enabled' => false]);

    $this->get('/ai-image', ['X-ShopWeb-Purpose' => 'storefront-prefetch'])
        ->assertNoContent()
        ->assertHeader('X-ShopWeb-Prefetch', 'authentication-skipped');

    $this->assertNull(session('url.intended'));

    $this->get('/ai-image')->assertRedirect('/login');

    expect(session('url.intended'))->toContain('/ai-image');
});

it('keeps dashboard display order while lazily loading realtime sections', function (): void {
    foreach ([
        LowStockVariants::class,
        OperationsHealthStats::class,
        SystemLoadStats::class,
        SystemLoadChart::class,
        VisitSourceOverview::class,
        AiChannelHealthWidget::class,
        LocalAiResourceWidget::class,
    ] as $widget) {
        expect($widget::isLazy())->toBeTrue();
    }

    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect(strpos($provider, 'SystemLoadStats::class'))->toBeLessThan(strpos($provider, 'ActionRequiredList::class'))
        ->and(strpos($provider, 'VisitSourceOverview::class'))->toBeLessThan(strpos($provider, 'PendingPaymentOrders::class'))
        ->and($provider)->toContain('adminPrefetchUrlGroups')
        ->toContain('maxPrimaryPerPage')
        ->toContain("document.addEventListener('livewire:navigating'")
        ->toContain('adminPrefetchRuntime.controller?.abort()');
});
