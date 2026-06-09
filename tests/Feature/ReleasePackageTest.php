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
        ->and($script)->toContain("'storage/app/ui-checks'");
});
