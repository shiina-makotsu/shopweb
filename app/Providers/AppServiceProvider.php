<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Announcement;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\PrivateMessage;
use App\Models\SiteSetting;
use App\Services\AdminLoginLogger;
use App\Services\CartService;
use App\Support\RelativeUrlRewriter;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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

        View::composer('*', function ($view): void {
            if (app()->runningInConsole() || ! $this->canReadSettings()) {
                return;
            }

            $view->with('siteSettings', SiteSetting::query()->first());

            if (request()->is('admin*', 'livewire*')) {
                return;
            }

            $cartItems = app(CartService::class)->items();

            $view->with([
                'storeCategories' => Category::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
                'storePages' => Page::query()->published()->orderBy('sort_order')->orderBy('title')->limit(8)->get(),
                'storeMenuItems' => NavigationMenuItem::query()
                    ->active()
                    ->whereNull('parent_id')
                    ->with(['children' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('label')])
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(),
                'cartItemCount' => $cartItems->sum('quantity'),
                'cartSubtotalCents' => $cartItems->sum('line_total_cents'),
                'unreadAnnouncementCount' => auth()->check()
                    ? Announcement::query()->published()->whereDoesntHave('reads', fn ($query) => $query->where('user_id', auth()->id()))->count()
                    : 0,
                'privateUnreadMessageCount' => $this->privateUnreadMessageCount(),
                'popupAnnouncement' => auth()->check()
                    ? Announcement::query()->published()->where('popup_when_unread', true)->whereDoesntHave('reads', fn ($query) => $query->where('user_id', auth()->id()))->orderByDesc('is_pinned')->latest('published_at')->first()
                    : null,
            ]);
        });
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

    private function canReadSettings(): bool
    {
        try {
            return file_exists(storage_path('app/install.lock')) && \Schema::hasTable('site_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
