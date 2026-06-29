<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\AdminLoginLogger;
use App\Services\DatabaseMigrationHealth;
use App\Services\StorefrontCache;
use App\Support\RelativeUrlRewriter;
use App\Support\StorefrontViewData;
use Filament\Actions\CreateAction;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    private ?bool $canReadSettingsCache = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->ensureRuntimeStoragePaths();
        $this->autoRepairDatabaseSchema();
        $this->configureFilamentActions();

        Blade::directive('money', function (string $expression): string {
            return "<?php echo App\\Support\\Money::format($expression); ?>";
        });

        Event::listen(Login::class, function (Login $event): void {
            app(AdminLoginLogger::class)->successful($event->user, request());
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AdminLoginLogger::class)->failed($event->credentials['email'] ?? null, request());
        });

        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            app(RelativeUrlRewriter::class)->rewriteResponse($event->response, $event->request);
        });

        foreach ([SiteSetting::class, Category::class, Page::class, NavigationMenuItem::class] as $model) {
            $model::saved(function (): void {
                app(StorefrontCache::class)->clear();
            });
            $model::deleted(function (): void {
                app(StorefrontCache::class)->clear();
            });
        }

        View::composer('*', function ($view): void {
            if (! $this->canReadSettings()) {
                return;
            }

            $storefrontViewData = app(StorefrontViewData::class);
            $siteSettings = $storefrontViewData->settings();

            if (request()->is('admin*', 'livewire*')) {
                $view->with('siteSettings', $siteSettings);

                return;
            }

            $view->with($storefrontViewData->data());
        });
    }

    private function configureFilamentActions(): void
    {
        CreateAction::configureUsing(function (CreateAction $action): void {
            $action
                ->modalSubmitActionLabel('保存')
                ->createAnotherAction(function (\Filament\Actions\Action $createAnotherAction) use ($action): \Filament\Actions\Action {
                    return $createAnotherAction->label('保存并创建新'.($action->getModelLabel() ?: '记录'));
                });
        });
    }

    private function canReadSettings(): bool
    {
        if ($this->canReadSettingsCache !== null) {
            return $this->canReadSettingsCache;
        }

        try {
            return $this->canReadSettingsCache = file_exists(storage_path('app/install.lock')) && \Schema::hasTable('site_settings');
        } catch (Throwable) {
            return $this->canReadSettingsCache = false;
        }
    }

    private function autoRepairDatabaseSchema(): void
    {
        if (! (bool) config('shop.auto_migrate_on_boot', true)) {
            return;
        }

        if (! file_exists(storage_path('app/install.lock'))) {
            return;
        }

        if ($this->runningMigrationCommand()) {
            return;
        }

        $stamp = storage_path('framework/shop_database_health_checked_at');
        $ttl = max(0, (int) config('shop.auto_migrate_check_ttl', 60));

        if ($ttl > 0 && File::exists($stamp) && (time() - (int) File::get($stamp)) < $ttl) {
            return;
        }

        File::ensureDirectoryExists(dirname($stamp));
        File::put($stamp, (string) time());

        $lockPath = storage_path('framework/shop_database_migration.lock');
        $lock = fopen($lockPath, 'c');

        if (! $lock) {
            return;
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                return;
            }

            $result = app(DatabaseMigrationHealth::class)->repair();

            if ($result['migrated']) {
                Log::info('Database migration health check repaired schema.', [
                    'ok' => $result['ok'],
                    'pending_before' => $result['pending_before'],
                    'pending_after' => $result['pending_after'],
                    'error' => $result['error'],
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Database migration health check failed.', ['exception' => $exception]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureRuntimeStoragePaths(): void
    {
        foreach ([
            storage_path('app/private'),
            storage_path('app/private/payment-proofs'),
            storage_path('app/private/livewire-tmp'),
            storage_path('app/private/support-attachments'),
            storage_path('app/private/private-attachments'),
            storage_path('app/private/digital-deliveries'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ] as $path) {
            try {
                File::ensureDirectoryExists($path, 0775, true);
            } catch (Throwable $exception) {
                Log::warning('Runtime storage path could not be ensured.', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function runningMigrationCommand(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        $command = implode(' ', $_SERVER['argv'] ?? []);

        return str_contains($command, 'migrate')
            || str_contains($command, 'shop:install')
            || str_contains($command, 'shop:database-health');
    }
}
