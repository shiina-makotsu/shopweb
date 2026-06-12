<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\DailySalesChart;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\LowStockVariants;
use App\Filament\Widgets\PendingPaymentOrders;
use App\Filament\Widgets\SalesRangeStats;
use App\Filament\Pages\BackupPage;
use App\Filament\Pages\AdminSearchPage;
use App\Filament\Pages\CacheManagementPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\MailSettingsPage;
use App\Filament\Pages\NotFoundContentPage;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Pages\ProfitOverviewPage;
use App\Filament\Pages\ProductDiscountPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StoreInfoPage;
use App\Filament\Pages\SupportAiSettingsPage;
use App\Filament\Pages\SystemInfoPage;
use App\Models\SiteSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Enums\UserMenuPosition;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName(fn (): string => $this->adminBrandName())
            ->brandLogo(fn (): ?string => $this->adminLogoUrl())
            ->brandLogoHeight('2rem')
            ->favicon(fn (): ?string => $this->adminFaviconUrl())
            ->login()
            ->userMenu(position: UserMenuPosition::Topbar)
            ->sidebarWidth('220px')
            ->collapsedSidebarWidth('4.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(true)
            ->navigationGroups([
                NavigationGroup::make('商品')->icon(Heroicon::OutlinedShoppingBag)->collapsible(true)->collapsed(),
                NavigationGroup::make('目录')->icon(Heroicon::OutlinedSquares2x2)->collapsible(true)->collapsed(),
                NavigationGroup::make('交易')->icon(Heroicon::OutlinedShoppingCart)->collapsible(true)->collapsed(),
                NavigationGroup::make('仓库')->icon(Heroicon::OutlinedArchiveBox)->collapsible(true)->collapsed(),
                NavigationGroup::make('财务')->icon(Heroicon::OutlinedBanknotes)->collapsible(true)->collapsed(),
                NavigationGroup::make('用户')->icon(Heroicon::OutlinedUsers)->collapsible(true)->collapsed(),
                NavigationGroup::make('客服')->icon(Heroicon::OutlinedLifebuoy)->collapsible(true)->collapsed(),
                NavigationGroup::make('内容')->icon(Heroicon::OutlinedDocumentText)->collapsible(true)->collapsed(),
                NavigationGroup::make('论坛')->icon(Heroicon::OutlinedChatBubbleLeftRight)->collapsible(true)->collapsed(),
                NavigationGroup::make('报告')->icon(Heroicon::OutlinedChartBarSquare)->collapsible(true)->collapsed(),
                NavigationGroup::make('系统')->icon(Heroicon::OutlinedCog6Tooth)->collapsible(true)->collapsed(),
            ])
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([
                Dashboard::class,
                AdminSearchPage::class,
                BackupPage::class,
                CacheManagementPage::class,
                HomeContentPage::class,
                MailSettingsPage::class,
                NotFoundContentPage::class,
                PaymentSettingsPage::class,
                ProfitOverviewPage::class,
                ProductDiscountPage::class,
                ReportsPage::class,
                StoreInfoPage::class,
                SupportAiSettingsPage::class,
                SystemInfoPage::class,
            ])
            ->widgets([
                DashboardStats::class,
                SalesRangeStats::class,
                DailySalesChart::class,
                PendingPaymentOrders::class,
                LowStockVariants::class,
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString($this->adminThemeStyle().<<<'HTML'
                    <script>
                        (() => {
                            const resetVersion = '2026-06-10-admin-sidebar-warehouse-v1';
                            const resetKey = 'shopweb:admin-sidebar-reset-version';
                            const defaultCollapsedGroups = ['商品', '目录', '交易', '仓库', '用户', '内容', '论坛', '报告', '系统'];

                            try {
                                if (window.innerWidth >= 1024 && localStorage.getItem(resetKey) !== resetVersion) {
                                    localStorage.setItem('isOpen', JSON.stringify(true));
                                    localStorage.setItem('isOpenDesktop', JSON.stringify(true));
                                    localStorage.setItem('collapsedGroups', JSON.stringify(defaultCollapsedGroups));
                                    localStorage.setItem(resetKey, resetVersion);
                                }
                            } catch (error) {
                                // Keep the admin usable when browser storage is blocked.
                            }

                            const makeMarkdownToolButton = (name, label, icon, action) => {
                                const button = document.createElement('button');

                                button.type = 'button';
                                button.tabIndex = -1;
                                button.className = `shop-md-tool-btn shop-md-tool-${name}`;
                                button.title = label;
                                button.setAttribute('aria-label', label);
                                button.innerHTML = `${icon}<span>${label}</span>`;
                                button.addEventListener('click', (event) => {
                                    event.preventDefault();
                                    action();
                                });

                                return button;
                            };

                            const setMarkdownMode = (editor, mode) => {
                                if (mode === 'edit') {
                                    if (editor.isPreviewActive()) {
                                        window.EasyMDE.togglePreview(editor);
                                    }

                                    if (editor.isSideBySideActive()) {
                                        window.EasyMDE.toggleSideBySide(editor);
                                    }

                                    return;
                                }

                                if (mode === 'preview') {
                                    if (editor.isSideBySideActive()) {
                                        window.EasyMDE.toggleSideBySide(editor);
                                    }

                                    if (! editor.isPreviewActive()) {
                                        window.EasyMDE.togglePreview(editor);
                                    }

                                    return;
                                }

                                if (editor.isPreviewActive()) {
                                    window.EasyMDE.togglePreview(editor);
                                }

                                editor.options.sideBySideFullscreen = false;

                                if (! editor.isSideBySideActive()) {
                                    window.EasyMDE.toggleSideBySide(editor);
                                }
                            };

                            const enhanceMarkdownEditors = () => {
                                window.requestAnimationFrame(() => {
                                    document.querySelectorAll('.fi-fo-markdown-editor .EasyMDEContainer .editor-toolbar').forEach((toolbar) => {
                                        if (toolbar.dataset.shopEnhanced === '1') {
                                            return;
                                        }

                                        const editorRoot = toolbar.closest('[role="group"]');
                                        const editor = editorRoot?._editor;

                                        if (! editor || ! window.EasyMDE) {
                                            return;
                                        }

                                        toolbar.dataset.shopEnhanced = '1';

                                        const tabs = document.createElement('div');
                                        tabs.className = 'shop-md-tabs';
                                        toolbar.appendChild(tabs);

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'edit',
                                            '编辑',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M4 14.5V17h2.5L15 8.5 12.5 6 4 14.5Zm13.1-8.6a1.2 1.2 0 0 0 0-1.7l-1.3-1.3a1.2 1.2 0 0 0-1.7 0l-1 1 2.5 2.5 1.1-1.1Z"/></svg>',
                                            () => setMarkdownMode(editor, 'edit'),
                                        ));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'preview',
                                            '预览',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M10 4.5c-3.9 0-7 3.1-8.4 5.5 1.4 2.4 4.5 5.5 8.4 5.5s7-3.1 8.4-5.5C17 7.6 13.9 4.5 10 4.5Zm0 9.2A3.7 3.7 0 1 1 10 6.3a3.7 3.7 0 0 1 0 7.4Zm0-1.5a2.2 2.2 0 1 0 0-4.4 2.2 2.2 0 0 0 0 4.4Z"/></svg>',
                                            () => setMarkdownMode(editor, 'preview'),
                                        ));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'side-by-side',
                                            '分屏预览',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M3.5 3A1.5 1.5 0 0 0 2 4.5v11A1.5 1.5 0 0 0 3.5 17h13a1.5 1.5 0 0 0 1.5-1.5v-11A1.5 1.5 0 0 0 16.5 3h-13ZM4 5h5.25v10H4V5Zm6.75 0H16v10h-5.25V5Z"/></svg>',
                                            () => setMarkdownMode(editor, 'side-by-side'),
                                        ));
                                    });
                                });
                            };

                            const markdownObserver = new MutationObserver(enhanceMarkdownEditors);

                            document.addEventListener('DOMContentLoaded', () => {
                                enhanceMarkdownEditors();

                                if (document.body) {
                                    markdownObserver.observe(document.body, { childList: true, subtree: true });
                                }
                            });

                            document.addEventListener('livewire:navigated', enhanceMarkdownEditors);
                        })();
                    </script>
                    HTML),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'admin',
            ]);
    }

    private function adminThemeStyle(): string
    {
        $primary = '#2D9CDB';
        $accent = '#F5A9B8';
        $canvas = '#FFF7FB';

        try {
            if (Schema::hasTable('site_settings')) {
                $settings = SiteSetting::query()->first();

                $primary = $this->validColor($settings?->primary_color, $primary);
                $accent = $this->validColor($settings?->accent_color, $accent);
                $canvas = $this->validColor($settings?->background_color, $canvas, allowLight: true);
            }
        } catch (Throwable) {
            // Database may be unavailable during first install.
        }

        return <<<HTML
            <style>
                :root {
                    --shop-admin-blue-strong: {$primary};
                    --shop-admin-pink: {$accent};
                    --shop-admin-pink-strong: color-mix(in srgb, {$accent}, #9f1744 30%);
                    --wp-admin-blue: {$primary};
                    --wp-admin-blue-dark: color-mix(in srgb, {$primary}, #000 18%);
                    --wp-admin-current: {$accent};
                    --shop-admin-accent: {$accent};
                    --wp-admin-canvas: {$canvas};
                }
            </style>
            HTML;
    }

    private function adminBrandName(): string
    {
        return $this->siteSettings()?->site_name ?: 'ShopWeb';
    }

    private function adminLogoUrl(): ?string
    {
        return $this->siteSettings()?->logoUrl();
    }

    private function adminFaviconUrl(): ?string
    {
        return $this->siteSettings()?->faviconUrl();
    }

    private function siteSettings(): ?SiteSetting
    {
        try {
            if (Schema::hasTable('site_settings')) {
                return SiteSetting::query()->first();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function validColor(?string $color, string $fallback, bool $allowLight = false): string
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color)) {
            return $fallback;
        }

        $color = strtoupper((string) $color);

        if (! $allowLight && $this->isVeryLight($color)) {
            return $fallback;
        }

        return $color;
    }

    private function isVeryLight(string $color): bool
    {
        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));
        $luminance = (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);

        return $luminance > 238;
    }
}
