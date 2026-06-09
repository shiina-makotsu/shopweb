<?php

$root = dirname(__DIR__);
$version = trim((string) ($_SERVER['argv'][1] ?? '1.0.0'));
$dist = $root.DIRECTORY_SEPARATOR.'dist';
$platforms = [
    'windows' => [
        'zip' => $dist.DIRECTORY_SEPARATOR."shopweb-v{$version}-windows.zip",
        'start_script' => 'start-windows.bat',
        'source_script' => $root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'release'.DIRECTORY_SEPARATOR.'start-windows.bat',
    ],
    'linux' => [
        'zip' => $dist.DIRECTORY_SEPARATOR."shopweb-v{$version}-linux.zip",
        'start_script' => 'start-linux.sh',
        'source_script' => $root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'release'.DIRECTORY_SEPARATOR.'start-linux.sh',
    ],
];

if (! extension_loaded('zip')) {
    fwrite(STDERR, "PHP zip extension is required.\n");
    exit(1);
}

if (! file_exists($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php missing. Run composer install first.\n");
    exit(1);
}

if (! file_exists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json')) {
    fwrite(STDERR, "public/build/manifest.json missing. Run npm run build first.\n");
    exit(1);
}

if (! is_dir($dist)) {
    mkdir($dist, 0777, true);
}

$excludeDirs = [
    '.git',
    '.tools',
    'dist',
    'node_modules',
    'public/uploads/media',
    'storage/app/ui-checks',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
];

$excludeFiles = [
    '.env',
    '.phpunit.result.cache',
    'database/database.sqlite',
    'storage/app/install.lock',
    'storage/logs/laravel.log',
];

foreach ($platforms as $platform => $config) {
    if (! file_exists($config['source_script'])) {
        fwrite(STDERR, "Release start script missing: {$config['source_script']}\n");
        exit(1);
    }
}

foreach ($platforms as $platform => $config) {
    $zipPath = $config['zip'];

    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Unable to create release zip: {$zipPath}\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));

        $skip = false;

        foreach ($excludeDirs as $dir) {
            if ($relative === $dir || str_starts_with($relative, $dir.'/')) {
                $skip = true;
                break;
            }
        }

        if ($skip || in_array($relative, $excludeFiles, true)) {
            continue;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($path, $relative);
        }
    }

    foreach ([
        'storage/app/.gitkeep',
        'storage/app/private/payment-proofs/.gitkeep',
        'storage/app/private/.gitkeep',
        'storage/framework/.gitkeep',
        'storage/framework/cache/.gitkeep',
        'storage/framework/cache/data/.gitkeep',
        'storage/framework/sessions/.gitkeep',
        'storage/framework/testing/.gitkeep',
        'storage/framework/views/.gitkeep',
        'storage/logs/.gitkeep',
        'public/uploads/.gitkeep',
    ] as $keep) {
        if ($zip->locateName($keep) === false) {
            $zip->addFromString($keep, '');
        }
    }

    if (! $zip->addFile($config['source_script'], $config['start_script'])) {
        fwrite(STDERR, "Unable to add start script: {$config['start_script']}\n");
        $zip->close();
        exit(1);
    }
    if ($platform === 'linux') {
        $zip->setExternalAttributesName($config['start_script'], ZipArchive::OPSYS_UNIX, 0100755 << 16);
    }

    $zip->close();

    $check = new ZipArchive();
    if ($check->open($zipPath) !== true) {
        fwrite(STDERR, "Unable to verify release zip: {$zipPath}\n");
        exit(1);
    }

    foreach ([
        'vendor/autoload.php',
        'public/build/manifest.json',
        'public/web.config',
        'public/.htaccess',
        'DEPLOY.md',
        'README.md',
        'docs/nginx.conf.example',
        $config['start_script'],
    ] as $required) {
        if ($check->locateName($required) === false) {
            fwrite(STDERR, "Release check failed: {$required} missing from {$zipPath}.\n");
            $check->close();
            exit(1);
        }
    }

    foreach ([
        '.env',
        'database/database.sqlite',
        'storage/app/install.lock',
        'node_modules/package.json',
    ] as $forbidden) {
        if ($check->locateName($forbidden) !== false) {
            fwrite(STDERR, "Release check failed: {$forbidden} must not be included in {$zipPath}.\n");
            $check->close();
            exit(1);
        }
    }

    $check->close();

    echo "Release created: {$zipPath}\n";
}
