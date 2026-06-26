<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\NavigationMenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\PrivateMessage;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Services\AdminLoginLogger;
use App\Services\CartService;
use App\Services\DatabaseMigrationHealth;
use App\Services\StorefrontCache;
use App\Support\RelativeUrlRewriter;
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

    private ?SiteSetting $sharedSiteSettings = null;

    private ?array $sharedStorefrontViewData = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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

            $siteSettings = $this->sharedSiteSettings();

            if (request()->is('admin*', 'livewire*')) {
                $view->with('siteSettings', $siteSettings);

                return;
            }

            $view->with($this->sharedStorefrontViewData());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedStorefrontViewData(): array
    {
        if ($this->sharedStorefrontViewData !== null) {
            return $this->sharedStorefrontViewData;
        }

        $storefrontCache = app(StorefrontCache::class);
        $cartItems = app(CartService::class)->items();
        $pendingPaymentOrderCount = $this->pendingPaymentOrderCount();
        $awaitingReceiptOrderCount = $this->awaitingReceiptOrderCount();

        return $this->sharedStorefrontViewData = [
            'siteSettings' => $this->sharedSiteSettings(),
            'storeCategories' => $storefrontCache->categories(),
            'storePages' => $storefrontCache->pages(),
            'storeMenuItems' => $storefrontCache->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV),
            'storeTopNavItems' => $storefrontCache->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV),
            'storeHomeInfoMenuItems' => $storefrontCache->menuItems(NavigationMenuItem::PLACEMENT_HOME_INFO),
            'cartItemCount' => $cartItems->sum('quantity'),
            'cartSubtotalCents' => $cartItems->sum('line_total_cents'),
            'unreadAnnouncementCount' => $this->unreadAnnouncementCount(),
            'privateUnreadMessageCount' => $this->privateUnreadMessageCount(),
            'supportUnreadMessageCount' => $this->supportUnreadMessageCount(),
            'pendingPaymentOrderCount' => $pendingPaymentOrderCount,
            'awaitingReceiptOrderCount' => $awaitingReceiptOrderCount,
            'userOrderNoticeCount' => $pendingPaymentOrderCount + $awaitingReceiptOrderCount,
            'popupAnnouncement' => $this->popupAnnouncement(),
        ];
    }

    private function sharedSiteSettings(): ?SiteSetting
    {
        return $this->sharedSiteSettings ??= app(StorefrontCache::class)->settings();
    }

    private function unreadAnnouncementCount(): int
    {
        try {
            if (! auth()->check() || ! \Schema::hasTable('announcements')) {
                return 0;
            }

            return Announcement::query()
                ->published()
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', auth()->id()))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function popupAnnouncement(): ?Announcement
    {
        try {
            if (! auth()->check() || ! \Schema::hasTable('announcements')) {
                return null;
            }

            return Announcement::query()
                ->published()
                ->where('popup_when_unread', true)
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', auth()->id()))
                ->orderByDesc('is_pinned')
                ->latest('published_at')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function privateUnreadMessageCount(): int
    {
        try {
            if (! auth()->check() || ! \Schema::hasTable('private_messages')) {
                return 0;
            }

            return PrivateMessage::query()
                ->where('recipient_id', auth()->id())
                ->whereNull('read_at')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function supportUnreadMessageCount(): int
    {
        try {
            if (! auth()->check() || ! \Schema::hasTable('support_chat_messages') || ! \Schema::hasTable('support_chat_sessions')) {
                return 0;
            }

            return SupportChatMessage::query()
                ->whereIn('sender_type', [SupportChatMessage::SENDER_ADMIN, SupportChatMessage::SENDER_SYSTEM])
                ->whereNull('read_at')
                ->whereHas('session', fn ($query) => $query
                    ->where('user_id', auth()->id())
                    ->whereIn('status', [SupportChatSession::STATUS_OPEN, SupportChatSession::STATUS_ACTIVE]))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function pendingPaymentOrderCount(): int
    {
        return $this->userOrderCount([Order::STATUS_PENDING_PAYMENT]);
    }

    private function awaitingReceiptOrderCount(): int
    {
        return $this->userOrderCount([Order::STATUS_AWAITING_RECEIPT]);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function userOrderCount(array $statuses): int
    {
        try {
            if (! auth()->check() || ! \Schema::hasTable('orders')) {
                return 0;
            }

            return Order::query()
                ->where('user_id', auth()->id())
                ->whereIn('status', $statuses)
                ->whereNull('user_deleted_at')
                ->count();
        } catch (Throwable) {
            return 0;
        }
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

    private function navigationMenuItems(string $placement)
    {
        try {
            if (! \Schema::hasTable('navigation_menu_items')) {
                return collect();
            }

            $hasPlacementColumn = \Schema::hasColumn('navigation_menu_items', 'placement');

            return NavigationMenuItem::query()
                ->active()
                ->when($hasPlacementColumn, fn ($query) => $query->placement($placement))
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query
                    ->active()
                    ->when($hasPlacementColumn, fn ($query) => $query->placement($placement))
                    ->orderBy('sort_order')
                    ->orderBy('label')])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();
        } catch (Throwable) {
            return collect();
        }
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
