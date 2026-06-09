<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class ShopInstaller
{
    public function install(array $data, bool $writeEnvironment = true, bool $optimize = true): void
    {
        if ($writeEnvironment) {
            $this->writeEnvironment($data);
        }

        $this->configureDatabase($data);

        Artisan::call('migrate', ['--force' => true]);

        SiteSetting::query()->firstOrCreate([], [
            'site_name' => $data['site_name'],
            'home_title' => $data['site_name'],
            'contact_info' => $data['contact_info'] ?? null,
            'payment_instructions' => $data['payment_instructions'] ?? null,
            'payment_gateway_provider' => 'manual',
            'payment_enabled_methods' => ['alipay_qr'],
        ])->update([
            'site_name' => $data['site_name'],
            'contact_info' => $data['contact_info'] ?? null,
            'payment_instructions' => $data['payment_instructions'] ?? null,
            'payment_gateway_provider' => 'manual',
        ]);

        User::query()->updateOrCreate(
            ['email' => $data['admin_email']],
            [
                'name' => $data['admin_name'],
                'public_id' => 'staff_admin',
                'password' => Hash::make($data['admin_password']),
                'role' => 'admin',
                'account_type' => 'regular',
            ],
        );

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/install.lock'), now()->toIso8601String());

        Artisan::call('optimize:clear');

        if ($optimize) {
            Artisan::call('optimize');
        }
    }

    public function configureDatabase(array $data): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.host', $data['db_host']);
        Config::set('database.connections.mysql.port', $data['db_port']);
        Config::set('database.connections.mysql.database', $data['db_database']);
        Config::set('database.connections.mysql.username', $data['db_username']);
        Config::set('database.connections.mysql.password', $data['db_password'] ?? '');
        DB::purge('mysql');
    }

    public function writeEnvironment(array $data): void
    {
        $env = [
            'APP_NAME' => $data['site_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => $data['app_url'],
            'APP_LOCALE' => 'zh_CN',
            'APP_FALLBACK_LOCALE' => 'zh_CN',
            'APP_FAKER_LOCALE' => 'zh_CN',
            'APP_TIMEZONE' => 'Asia/Shanghai',
            'LOG_CHANNEL' => 'stack',
            'LOG_LEVEL' => 'debug',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'database',
            'FILESYSTEM_DISK' => 'local',
            'SHOP_INSTALLER_ENABLED' => 'false',
        ];

        $content = collect($env)
            ->map(fn (string $value, string $key): string => $key.'='.$this->envValue($value))
            ->implode(PHP_EOL);

        File::put(base_path('.env'), $content.PHP_EOL);
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|=|"|\'/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
