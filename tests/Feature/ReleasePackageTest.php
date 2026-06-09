<?php

it('keeps release package rules aligned with deployment requirements', function (): void {
    $script = file_get_contents(base_path('scripts/build-release.php'));

    expect($script)->toContain('vendor/autoload.php')
        ->and($script)->toContain('public/build/manifest.json')
        ->and($script)->toContain('shopweb-v{$version}-windows.zip')
        ->and($script)->toContain('shopweb-v{$version}-linux.zip')
        ->and($script)->toContain('start-windows.bat')
        ->and($script)->toContain('start-linux.sh')
        ->and($script)->toContain("'public/web.config'")
        ->and($script)->toContain("'public/.htaccess'")
        ->and($script)->toContain("'DEPLOY.md'")
        ->and($script)->toContain("'docs/nginx.conf.example'")
        ->and($script)->toContain("'.env'")
        ->and($script)->toContain("'node_modules'")
        ->and($script)->toContain("'storage/framework/sessions'")
        ->and($script)->toContain("'storage/app/private/livewire-tmp/.gitkeep'")
        ->and($script)->toContain("'storage/app/ui-checks'");
});

it('configures livewire temporary uploads for deployed servers', function (): void {
    expect(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(config('livewire.temporary_file_upload.directory'))->toBe('livewire-tmp')
        ->and(config('livewire.temporary_file_upload.rules'))->toBe(['required', 'file', 'max:65536']);
});
