<?php

it('builds storefront features as isolated dynamic modules', function (): void {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $entry = $manifest['resources/js/app.js'] ?? [];
    $dynamicImports = $entry['dynamicImports'] ?? [];

    expect($entry['isEntry'] ?? false)->toBeTrue()
        ->and($dynamicImports)->toContain(
            'resources/js/storefront/ui.js',
            'resources/js/storefront/font-awesome-picker.js',
            'resources/js/storefront/guide-pet.js',
            'resources/js/storefront/cart-actions.js',
            'resources/js/storefront/navigation-prefetch.js',
        )
        ->and($manifest['resources/js/admin.js']['isEntry'] ?? false)->toBeTrue();
});

it('uses shared fault and prefetch infrastructure across modules', function (): void {
    $runtime = file_get_contents(resource_path('js/core/runtime.js'));
    $queue = file_get_contents(resource_path('js/core/prefetch-queue.js'));
    $storefront = file_get_contents(resource_path('js/storefront/navigation-prefetch.js'));
    $admin = file_get_contents(resource_path('js/admin.js'));

    expect($runtime)
        ->toContain('shopweb:module-error')
        ->toContain('result instanceof Promise')
        ->and($queue)->toContain('export const createPrefetchQueue')
        ->and($storefront)->toContain("from '../core/prefetch-queue'")
        ->and($admin)->toContain("from './core/prefetch-queue'");
});

it('removes superseded dashboard widgets and axios bootstrap code', function (): void {
    expect(file_exists(app_path('Filament/Widgets/DashboardStats.php')))->toBeFalse()
        ->and(file_exists(app_path('Filament/Widgets/SalesRangeStats.php')))->toBeFalse()
        ->and(file_exists(resource_path('js/bootstrap.js')))->toBeFalse()
        ->and(file_get_contents(base_path('package.json')))->not->toContain('axios');
});
