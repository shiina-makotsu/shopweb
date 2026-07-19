<?php

use App\Filament\Widgets\AiChannelHealthWidget;
use App\Filament\Widgets\LocalAiResourceWidget;
use App\Filament\Widgets\LowStockVariants;
use App\Filament\Widgets\OperationsHealthStats;
use App\Filament\Widgets\SystemLoadChart;
use App\Filament\Widgets\SystemLoadStats;
use App\Filament\Widgets\VisitSourceOverview;

it('waits for the storefront page before progressively prefetching navigation', function (): void {
    $entry = file_get_contents(resource_path('js/app.js'));
    $script = file_get_contents(resource_path('js/storefront/navigation-prefetch.js'));
    $queue = file_get_contents(resource_path('js/core/prefetch-queue.js'));

    expect($entry)
        ->toContain("import('./storefront/navigation-prefetch')")
        ->toContain('runIsolatedModule')
        ->and($script)
        ->toContain("storagePrefix: 'shopweb:storefront-prefetch'")
        ->toContain("window.addEventListener('load'")
        ->toContain("window.requestIdleCallback(start")
        ->and($queue)
        ->toContain("'X-ShopWeb-Purpose': purpose")
        ->toContain("['slow-2g', '2g']")
        ->toContain('state.active || state.queue.length === 0');
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
    $adminScript = file_get_contents(resource_path('js/admin.js'));

    expect(strpos($provider, 'SystemLoadStats::class'))->toBeLessThan(strpos($provider, 'ActionRequiredList::class'))
        ->and(strpos($provider, 'VisitSourceOverview::class'))->toBeLessThan(strpos($provider, 'PendingPaymentOrders::class'))
        ->and($provider)->toContain('adminModuleTag')
        ->toContain('Vite asset missing')
        ->not->toContain('adminPrefetchRuntime')
        ->and($adminScript)->toContain("storagePrefix: 'shopweb:admin-prefetch'")
        ->toContain('groupedUrls')
        ->toContain("document.addEventListener('livewire:navigating'")
        ->toContain('queue.pause({ clear: true })');
});
