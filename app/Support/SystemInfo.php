<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemInfo
{
    /**
     * @return array<int, array{label:string,value:string,status:?bool}>
     */
    public function runtime(): array
    {
        return [
            ['label' => 'PHP', 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['label' => 'Laravel', 'value' => app()->version(), 'status' => null],
            ['label' => '环境', 'value' => app()->environment(), 'status' => app()->environment('production')],
            ['label' => '调试模式', 'value' => config('app.debug') ? '开启' : '关闭', 'status' => ! config('app.debug')],
            ['label' => '时区', 'value' => config('app.timezone'), 'status' => config('app.timezone') === 'Asia/Shanghai'],
            ['label' => '语言', 'value' => config('app.locale'), 'status' => config('app.locale') === 'zh_CN'],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string,status:?bool}>
     */
    public function database(): array
    {
        $default = config('database.default');
        $connection = config("database.connections.{$default}", []);
        $ok = false;
        $version = '未连接';

        try {
            if (($connection['driver'] ?? null) === 'sqlite') {
                $version = (string) DB::selectOne('select sqlite_version() as version')?->version;
            } else {
                $version = (string) (DB::selectOne('select version() as version')?->version ?? '已连接');
            }

            $ok = true;
        } catch (\Throwable $exception) {
            $version = $exception->getMessage();
        }

        return [
            ['label' => '默认连接', 'value' => (string) $default, 'status' => $ok],
            ['label' => '驱动', 'value' => (string) ($connection['driver'] ?? '-'), 'status' => null],
            ['label' => '数据库', 'value' => (string) ($connection['database'] ?? '-'), 'status' => null],
            ['label' => '版本/状态', 'value' => $version, 'status' => $ok],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string,status:bool}>
     */
    public function writablePaths(): array
    {
        $paths = [
            '.env' => File::exists(base_path('.env')) ? base_path('.env') : base_path(),
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public/uploads' => public_path('uploads'),
            'storage/app/private' => storage_path('app/private'),
            'storage/app/private/payment-proofs' => storage_path('app/private/payment-proofs'),
        ];

        return collect($paths)
            ->map(fn (string $path, string $label): array => [
                'label' => $label,
                'value' => $path,
                'status' => File::exists($path) && File::isWritable($path),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,value:string,status:?bool}>
     */
    public function installer(): array
    {
        $lockPath = storage_path('app/install.lock');

        return [
            ['label' => '安装锁', 'value' => File::exists($lockPath) ? '已创建' : '未创建', 'status' => File::exists($lockPath)],
            ['label' => '安装锁路径', 'value' => $lockPath, 'status' => null],
            ['label' => '重新安装开关', 'value' => config('shop.installer_enabled') ? '开启' : '关闭', 'status' => ! config('shop.installer_enabled')],
        ];
    }
}
