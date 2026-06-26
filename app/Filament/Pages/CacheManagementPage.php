<?php

namespace App\Filament\Pages;

use App\Services\SystemCacheManager;
use App\Services\SystemUpdateService;
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
    public ?string $rollbackCommit = null;

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

    public function prewarm(): void
    {
        $this->finish('访问缓存已预热', app(SystemCacheManager::class)->prewarm());
    }

    /**
     * @return array<string, string>
     */
    public function rollbackOptions(): array
    {
        return collect(app(SystemUpdateService::class)->recentVersions())
            ->mapWithKeys(fn (array $version): array => [$version['hash'] => $version['label']])
            ->all();
    }

    public function pullUpdates(): void
    {
        $this->finishUpdate(app(SystemUpdateService::class)->pullAndBuild());
    }

    public function rollback(): void
    {
        if (! filled($this->rollbackCommit)) {
            Notification::make()
                ->title('请先选择要回滚的版本。')
                ->warning()
                ->send();

            return;
        }

        $this->finishUpdate(app(SystemUpdateService::class)->rollbackTo($this->rollbackCommit));
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

    /**
     * @param  array{status: string, title: string, lines: array<int, string>}  $result
     */
    private function finishUpdate(array $result): void
    {
        $this->lastResult = implode("\n\n", $result['lines']);

        $notification = Notification::make()->title($result['title']);

        match ($result['status']) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'info' => $notification->info(),
            default => $notification->danger(),
        };

        $notification->send();
    }
}
