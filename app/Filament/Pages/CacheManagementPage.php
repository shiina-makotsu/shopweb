<?php

namespace App\Filament\Pages;

use App\Services\SystemCacheManager;
use App\Support\AdminAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CacheManagementPage extends Page
{
    protected static ?string $navigationLabel = '缓存管理';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;
    protected static ?int $navigationSort = 75;
    protected static ?string $slug = 'cache-management';
    protected string $view = 'filament.pages.cache-management';

    public ?string $lastResult = null;

    public function getTitle(): string
    {
        return '缓存管理';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    /**
     * @return array<int, array{label:string,value:string,status:?bool}>
     */
    public function overview(): array
    {
        return app(SystemCacheManager::class)->overview();
    }

    public function clearAll(): void
    {
        $this->finish('缓存已清理', app(SystemCacheManager::class)->clearAll());
    }

    public function clearRuntime(): void
    {
        $this->finish('运行缓存已清理', app(SystemCacheManager::class)->clearRuntime());
    }

    public function optimize(): void
    {
        $this->finish('生产缓存已生成', app(SystemCacheManager::class)->optimize());
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function finish(string $title, array $lines): void
    {
        $this->lastResult = implode("\n", $lines);

        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }
}
