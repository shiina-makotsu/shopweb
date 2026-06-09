<?php

$root = dirname(__DIR__);
$version = trim((string) ($_SERVER['argv'][1] ?? '1.0.0'));
$dist = $root.DIRECTORY_SEPARATOR.'dist';
$zipPath = $dist.DIRECTORY_SEPARATOR."shopweb-v{$version}.zip";

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

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$excludeDirs = [
    '.git',
    '.tools',
    'dist',
    'node_modules',
    'storage/app/ui-checks',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
];

$excludeFiles = [
    '.env',
    '.phpunit.result.cache',
    'storage/app/install.lock',
    'storage/logs/laravel.log',
];

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

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
    'storage/app/private/payment-proofs/.gitkeep',
    'storage/app/private/.gitkeep',
    'storage/framework/cache/.gitkeep',
    'storage/framework/cache/data/.gitkeep',
    'storage/framework/sessions/.gitkeep',
    'storage/framework/views/.gitkeep',
    'storage/logs/.gitkeep',
    'public/uploads/.gitkeep',
] as $keep) {
    if (! $zip->locateName($keep)) {
        $zip->addFromString($keep, '');
    }
}

$zip->close();

$check = new ZipArchive();
$check->open($zipPath);

foreach ([
    'vendor/autoload.php',
    'public/build/manifest.json',
    'public/web.config',
    'public/.htaccess',
    'DEPLOY.md',
    'docs/nginx.conf.example',
] as $required) {
    if ($check->locateName($required) === false) {
        fwrite(STDERR, "Release check failed: {$required} missing from ZIP.\n");
        $check->close();
        exit(1);
    }
}

$check->close();

echo "Release created: {$zipPath}\n";
