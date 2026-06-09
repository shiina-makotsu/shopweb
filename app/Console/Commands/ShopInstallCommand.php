<?php

namespace App\Console\Commands;

use App\Services\ShopInstaller;
use Illuminate\Console\Command;

class ShopInstallCommand extends Command
{
    protected $signature = 'shop:install
        {--db-host=127.0.0.1}
        {--db-port=3306}
        {--db-database=shopweb}
        {--db-username=root}
        {--db-password=}
        {--app-url=http://localhost}
        {--site-name=ShopWeb}
        {--admin-name=Admin}
        {--admin-email=admin@example.com}
        {--admin-password= : Minimum 8 characters}';

    protected $description = 'Install ShopWeb from the command line.';

    public function handle(): int
    {
        $password = (string) ($this->option('admin-password') ?: $this->secret('管理员密码'));

        if (strlen($password) < 8) {
            $this->error('管理员密码至少 8 位。');

            return self::FAILURE;
        }

        app(ShopInstaller::class)->install([
            'db_host' => $this->option('db-host'),
            'db_port' => (int) $this->option('db-port'),
            'db_database' => $this->option('db-database'),
            'db_username' => $this->option('db-username'),
            'db_password' => $this->option('db-password'),
            'app_url' => $this->option('app-url'),
            'site_name' => $this->option('site-name'),
            'admin_name' => $this->option('admin-name'),
            'admin_email' => $this->option('admin-email'),
            'admin_password' => $password,
            'contact_info' => null,
            'payment_instructions' => null,
        ]);

        $this->info('ShopWeb 安装完成。');

        return self::SUCCESS;
    }
}
