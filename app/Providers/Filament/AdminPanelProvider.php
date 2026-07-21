<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ActionRequiredList;
use App\Filament\Widgets\AiChannelHealthWidget;
use App\Filament\Widgets\LocalAiResourceWidget;
use App\Filament\Widgets\LowStockVariants;
use App\Filament\Widgets\OperationsHealthStats;
use App\Filament\Widgets\PendingPaymentOrders;
use App\Filament\Widgets\SystemLoadChart;
use App\Filament\Widgets\SystemLoadStats;
use App\Filament\Widgets\VisitSourceOverview;
use App\Filament\Pages\BackupPage;
use App\Filament\Pages\AdminSearchPage;
use App\Filament\Pages\CacheManagementPage;
use App\Filament\Pages\CurrencySettingsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GuideAiSettingsPage;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\LoadingPageSettingsPage;
use App\Filament\Pages\MailSettingsPage;
use App\Filament\Pages\NotFoundContentPage;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Pages\ProfitOverviewPage;
use App\Filament\Pages\ProductDiscountPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StoreInfoPage;
use App\Filament\Pages\SupportAiSettingsPage;
use App\Filament\Pages\SystemInfoPage;
use App\Filament\Pages\UserAiPage;
use App\Filament\Pages\WalletSettingsPage;
use App\Models\SiteSetting;
use App\Services\AdminNotificationSummary;
use App\Services\StorefrontCache;
use App\Support\AdminMenuRegistry;
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
use Illuminate\Support\Facades\Vite;
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
            ->navigationGroups(app(AdminMenuRegistry::class)->navigationGroups())
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
                CurrencySettingsPage::class,
                GuideAiSettingsPage::class,
                HomeContentPage::class,
                LoadingPageSettingsPage::class,
                MailSettingsPage::class,
                NotFoundContentPage::class,
                PaymentSettingsPage::class,
                ProfitOverviewPage::class,
                ProductDiscountPage::class,
                ReportsPage::class,
                StoreInfoPage::class,
                SupportAiSettingsPage::class,
                SystemInfoPage::class,
                UserAiPage::class,
                WalletSettingsPage::class,
            ])
            ->widgets([
                SystemLoadStats::class,
                OperationsHealthStats::class,
                SystemLoadChart::class,
                VisitSourceOverview::class,
                AiChannelHealthWidget::class,
                LocalAiResourceWidget::class,
                ActionRequiredList::class,
                PendingPaymentOrders::class,
                LowStockVariants::class,
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    str_replace(
                        ['__SHOPWEB_ADMIN_GROUP_BADGES__', '__SHOPWEB_ADMIN_NOTIFICATION_URL__', '__SHOPWEB_ADMIN_MENU_CONFIG__', '__SHOPWEB_ADMIN_MODULE_TAG__'],
                        [
                            json_encode(app(AdminNotificationSummary::class)->data()['groups'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                            json_encode(route('admin.notification-summary', absolute: false), JSON_UNESCAPED_SLASHES) ?: '"/admin/notification-summary"',
                            json_encode(app(AdminMenuRegistry::class)->browserConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"groups":[],"items":[]}',
                            $this->adminModuleTag(),
                        ],
                        $this->adminThemeStyle().<<<'HTML'
                    <script>
                        (() => {
                            const resetVersion = '2026-06-10-admin-sidebar-warehouse-v1';
                            const resetKey = 'shopweb:admin-sidebar-reset-version';
                            const adminMenuConfig = __SHOPWEB_ADMIN_MENU_CONFIG__;
                            window.shopwebAdminRuntime = { menu: adminMenuConfig };
                            const defaultCollapsedGroups = (adminMenuConfig.groups || []).map((group) => group.label).filter(Boolean);
                            let groupBadges = __SHOPWEB_ADMIN_GROUP_BADGES__;
                            const notificationSummaryUrl = __SHOPWEB_ADMIN_NOTIFICATION_URL__;
                            const reportAdminModuleError = (name, error) => {
                                console.error(`[ShopWeb:${name}]`, error);
                                window.dispatchEvent(new CustomEvent('shopweb:module-error', {
                                    detail: { name, message: error instanceof Error ? error.message : String(error) },
                                }));
                            };
                            const safeAdminHandler = (name, handler) => (...args) => {
                                try {
                                    return handler(...args);
                                } catch (error) {
                                    reportAdminModuleError(name, error);
                                    return undefined;
                                }
                            };

                            const normalizeUrl = (url) => {
                                try {
                                    return new URL(url, window.location.origin).pathname.replace(/\/+$/, '') || '/';
                                } catch (error) {
                                    return String(url || '').split('?')[0].replace(/\/+$/, '') || '/';
                                }
                            };

                            const findSidebarGroup = (label) => document.querySelector(`.fi-sidebar-group[data-group-label="${CSS.escape(label)}"]`);

                            const findSidebarItem = (url, label) => {
                                const path = normalizeUrl(url);
                                const links = Array.from(document.querySelectorAll('.fi-sidebar a[href]'));

                                return links.find((link) => normalizeUrl(link.href) === path)
                                    || links.find((link) => label && link.textContent.trim().includes(label));
                            };

                            const syncAdminMenuOrder = () => {
                                const sidebar = document.querySelector('.fi-sidebar');

                                if (! sidebar) {
                                    return;
                                }

                                const groups = adminMenuConfig.groups || [];
                                const items = adminMenuConfig.items || [];
                                const groupContainer = document.querySelector('.fi-sidebar-nav-groups, .fi-sidebar-nav, nav.fi-sidebar-nav');

                                if (groupContainer) {
                                    groups.forEach((group) => {
                                        const node = findSidebarGroup(group.label);

                                        if (node) {
                                            groupContainer.appendChild(node);
                                        }
                                    });
                                }

                                items.forEach((item) => {
                                    const link = findSidebarItem(item.url, item.label);
                                    const targetGroup = item.group ? findSidebarGroup(item.group) : null;

                                    if (! link || ! targetGroup) {
                                        return;
                                    }

                                    const listItem = link.closest('li, .fi-sidebar-item');
                                    const list = targetGroup.querySelector('ul, .fi-sidebar-group-items, .fi-sidebar-nav-items');

                                    if (list && listItem && ! list.contains(listItem)) {
                                        list.appendChild(listItem);
                                    }
                                });
                            };

                            const syncNavigationGroupBadges = () => {
                                Object.entries(groupBadges).forEach(([label, count]) => {
                                    const selector = `.fi-sidebar-group[data-group-label="${CSS.escape(label)}"]`;
                                    const group = document.querySelector(selector);

                                    if (! group) {
                                        return;
                                    }

                                    const button = group.querySelector('.fi-sidebar-group-btn');

                                    if (! button) {
                                        return;
                                    }

                                    let badge = button.querySelector('[data-shopweb-group-badge]');

                                    if (! count) {
                                        badge?.remove();

                                        return;
                                    }

                                    if (! badge) {
                                        badge = document.createElement('span');
                                        badge.dataset.shopwebGroupBadge = 'true';
                                        badge.className = 'shopweb-admin-group-badge';
                                        button.insertBefore(badge, button.querySelector('.fi-sidebar-group-collapse-btn'));
                                    }

                                    badge.textContent = count > 99 ? '99+' : String(count);
                                    badge.setAttribute('aria-label', `${label}待处理 ${badge.textContent}`);
                                });
                            };

                            const syncNavigationItemBadges = (items = []) => {
                                items.forEach((item) => {
                                    const link = findSidebarItem(item.url, item.label);
                                    const container = link?.closest('.fi-sidebar-item, li');

                                    if (! link || ! container) {
                                        return;
                                    }

                                    let badge = container.querySelector('[data-shopweb-live-badge], .fi-badge');

                                    if (! item.count) {
                                        badge?.remove();
                                        return;
                                    }

                                    if (! badge) {
                                        badge = document.createElement('span');
                                        badge.className = 'shopweb-admin-item-badge';
                                        link.appendChild(badge);
                                    }

                                    badge.dataset.shopwebLiveBadge = 'true';
                                    badge.textContent = item.badge || (item.count > 99 ? '99+' : String(item.count));
                                    badge.setAttribute('aria-label', `${item.label}待处理 ${badge.textContent}`);
                                });
                            };

                            let notificationRefreshTimer = null;
                            let notificationRefreshController = null;

                            const refreshAdminNotifications = async (fresh = false) => {
                                if (document.hidden || ! notificationSummaryUrl || ! document.querySelector('.fi-sidebar')) {
                                    return;
                                }

                                notificationRefreshController?.abort();
                                notificationRefreshController = new AbortController();

                                try {
                                    const separator = notificationSummaryUrl.includes('?') ? '&' : '?';
                                    const response = await fetch(`${notificationSummaryUrl}${fresh ? `${separator}fresh=1` : ''}`, {
                                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        signal: notificationRefreshController.signal,
                                    });

                                    if (! response.ok) {
                                        return;
                                    }

                                    const summary = await response.json();
                                    groupBadges = summary.groups || {};
                                    syncNavigationGroupBadges();
                                    syncNavigationItemBadges(summary.items || []);
                                } catch (error) {
                                    // Navigation and tab suspension may cancel a harmless badge refresh.
                                }
                            };

                            const scheduleNotificationRefresh = (fresh = false, delay = 200) => {
                                window.clearTimeout(notificationRefreshTimer);
                                notificationRefreshTimer = window.setTimeout(() => refreshAdminNotifications(fresh), delay);
                            };

                            const bindLivewireNotificationRefresh = () => {
                                if (! window.Livewire?.hook || window.shopwebAdminNotificationHookBound) {
                                    return;
                                }

                                window.shopwebAdminNotificationHookBound = true;
                                window.Livewire.hook('commit', ({ succeed }) => {
                                    succeed(() => scheduleNotificationRefresh(true, 100));
                                });
                            };

                            safeAdminHandler('admin-sidebar', () => {
                                syncAdminMenuOrder();
                                syncNavigationGroupBadges();
                                document.addEventListener('DOMContentLoaded', safeAdminHandler('admin-sidebar-badges', syncNavigationGroupBadges));
                                document.addEventListener('DOMContentLoaded', safeAdminHandler('admin-sidebar-order', syncAdminMenuOrder));
                                document.addEventListener('livewire:navigated', safeAdminHandler('admin-sidebar-badges', syncNavigationGroupBadges));
                                document.addEventListener('livewire:navigated', safeAdminHandler('admin-sidebar-order', syncAdminMenuOrder));
                                document.addEventListener('livewire:update', safeAdminHandler('admin-sidebar-badges', syncNavigationGroupBadges));
                                document.addEventListener('livewire:update', safeAdminHandler('admin-sidebar-order', syncAdminMenuOrder));
                                document.addEventListener('DOMContentLoaded', () => scheduleNotificationRefresh(false, 0));
                                document.addEventListener('livewire:navigated', () => scheduleNotificationRefresh(false, 50));
                                document.addEventListener('livewire:update', () => scheduleNotificationRefresh(true, 250));
                                document.addEventListener('livewire:init', bindLivewireNotificationRefresh);
                                document.addEventListener('DOMContentLoaded', bindLivewireNotificationRefresh);
                                document.addEventListener('click', (event) => {
                                    if (! event.target.closest('[wire\\:click], .fi-modal button[type="submit"]')) {
                                        return;
                                    }

                                    window.setTimeout(() => refreshAdminNotifications(true), 900);
                                    window.setTimeout(() => refreshAdminNotifications(true), 1800);
                                }, true);
                                window.addEventListener('shopweb:admin-notifications-refresh', () => scheduleNotificationRefresh(true, 0));
                                document.addEventListener('visibilitychange', () => {
                                    if (! document.hidden) {
                                        scheduleNotificationRefresh(true, 0);
                                    }
                                });
                                window.setInterval(() => refreshAdminNotifications(false), 15000);
                            })();

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
                                button.className = `shop-md-tool-btn shop-md-tool-${name} no-disable`;
                                button.title = label;
                                button.setAttribute('aria-label', label);
                                button.innerHTML = `${icon}<span>${label}</span>`;
                                button.addEventListener('click', (event) => {
                                    event.preventDefault();
                                    action();
                                });

                                return button;
                            };

                            const noopMarkdownToolbarButton = {
                                classList: {
                                    add: () => {},
                                    remove: () => {},
                                    toggle: () => {},
                                },
                            };

                            const prepareMarkdownEditorForCustomPreview = (editor) => {
                                if (! editor) {
                                    return;
                                }

                                if (! editor.toolbarElements) {
                                    editor.toolbarElements = {};
                                }

                                editor.toolbarElements.preview = editor.toolbarElements.preview || noopMarkdownToolbarButton;
                                editor.toolbarElements['side-by-side'] = editor.toolbarElements['side-by-side'] || noopMarkdownToolbarButton;
                                editor.toolbar_div = editor.toolbar_div || noopMarkdownToolbarButton;
                            };

                            const getMarkdownPreviewClassNames = (editor) => {
                                const classNames = ['editor-preview'];
                                const configured = editor?.options?.previewClass;

                                if (Array.isArray(configured)) {
                                    classNames.push(...configured);
                                } else if (typeof configured === 'string') {
                                    classNames.push(...configured.split(' ').filter(Boolean));
                                }

                                return [...new Set(classNames)];
                            };

                            const ensureFullMarkdownPreview = (editor) => {
                                const wrapper = editor?.codemirror?.getWrapperElement();

                                if (! wrapper) {
                                    return null;
                                }

                                let preview = Array.from(wrapper.children)
                                    .find((element) => element.classList?.contains('editor-preview-full'));

                                if (! preview) {
                                    preview = document.createElement('div');
                                    preview.className = 'editor-preview-full';
                                    wrapper.appendChild(preview);
                                }

                                preview.classList.add(...getMarkdownPreviewClassNames(editor));

                                return preview;
                            };

                            const ensureSideMarkdownPreview = (editor) => {
                                const wrapper = editor?.codemirror?.getWrapperElement();

                                if (! wrapper) {
                                    return null;
                                }

                                let preview = Array.from(wrapper.parentElement?.children ?? [])
                                    .find((element) => element.classList?.contains('editor-preview-side'));

                                if (! preview) {
                                    preview = document.createElement('div');
                                    preview.className = 'editor-preview-side';
                                    wrapper.insertAdjacentElement('afterend', preview);
                                }

                                preview.classList.add(...getMarkdownPreviewClassNames(editor));

                                return preview;
                            };

                            const escapeMarkdownHtml = (value) => String(value ?? '')
                                .replaceAll('&', '&amp;')
                                .replaceAll('<', '&lt;')
                                .replaceAll('>', '&gt;')
                                .replaceAll('"', '&quot;')
                                .replaceAll("'", '&#039;');

                            const renderFontAwesomeShortcodes = (value) => String(value ?? '').replace(
                                /\[fa:(?:(solid|regular|brands):)?([a-z0-9][a-z0-9-]*)(?:\s+([^\]]{1,80}))?\]/gi,
                                (_, style = 'solid', icon, label = '') => {
                                    const family = { solid: 'fa-solid', regular: 'fa-regular', brands: 'fa-brands' }[String(style).toLowerCase()] || 'fa-solid';
                                    const classes = `${family} fa-${icon.toLowerCase()} fa-fw markdown-icon`;
                                    const trimmedLabel = String(label || '').trim();

                                    return trimmedLabel
                                        ? `<i class="${classes}" role="img" aria-label="${escapeMarkdownHtml(trimmedLabel)}"></i>`
                                        : `<i class="${classes}" aria-hidden="true"></i>`;
                                },
                            );

                            const fontAwesomeIconOptions = [
                                ['fish-fins', '鱼板/鱼', 'solid'],
                                ['cart-shopping', '购物车', 'solid'],
                                ['heart', '爱心', 'regular'],
                                ['star', '星标', 'regular'],
                                ['bell', '公告', 'solid'],
                                ['truck', '物流', 'solid'],
                                ['box-open', '发货', 'solid'],
                                ['wallet', '钱包', 'solid'],
                                ['ticket', '优惠券', 'solid'],
                                ['gift', '奖励', 'solid'],
                                ['circle-check', '完成', 'regular'],
                                ['clock', '等待', 'regular'],
                                ['triangle-exclamation', '提醒', 'solid'],
                                ['comment', '评论', 'regular'],
                                ['paper-plane', '发送', 'solid'],
                                ['image', '图片', 'regular'],
                                ['user', '用户', 'regular'],
                                ['users', '用户组', 'solid'],
                                ['paypal', 'PayPal', 'brands'],
                                ['github', 'GitHub', 'brands'],
                            ];

                            const fontAwesomeShortcode = (icon, style = 'solid', label = '') => {
                                const normalizedIcon = String(icon || '').trim().toLowerCase().replace(/^fa-/, '');
                                const normalizedStyle = ['solid', 'regular', 'brands'].includes(String(style).toLowerCase()) ? String(style).toLowerCase() : 'solid';
                                const normalizedLabel = String(label || '').trim();

                                if (! /^[a-z0-9][a-z0-9-]*$/.test(normalizedIcon)) {
                                    return '';
                                }

                                return normalizedStyle === 'solid'
                                    ? `[fa:${normalizedIcon}${normalizedLabel ? ` ${normalizedLabel}` : ''}]`
                                    : `[fa:${normalizedStyle}:${normalizedIcon}${normalizedLabel ? ` ${normalizedLabel}` : ''}]`;
                            };

                            const insertIntoMarkdownEditor = (editor, text) => {
                                if (! editor?.codemirror || ! text) {
                                    return;
                                }

                                editor.codemirror.replaceSelection(text);
                                editor.codemirror.focus();
                                editor.codemirror.save();
                                renderMarkdownPreview(editor);
                            };

                            const openFontAwesomePicker = (onSelect) => {
                                document.querySelector('[data-shop-fa-picker]')?.remove();

                                const overlay = document.createElement('div');
                                overlay.dataset.shopFaPicker = 'true';
                                overlay.className = 'shop-fa-picker-overlay';
                                overlay.innerHTML = `
                                    <div class="shop-fa-picker" role="dialog" aria-modal="true" aria-label="选择 Font Awesome 图标">
                                        <div class="shop-fa-picker-head">
                                            <strong>选择 Font Awesome 图标</strong>
                                            <button type="button" data-shop-fa-close aria-label="关闭">×</button>
                                        </div>
                                        <div class="shop-fa-picker-fields">
                                            <input data-shop-fa-search placeholder="搜索常用图标或输入图标名，如 fish-fins" autocomplete="off">
                                            <select data-shop-fa-style>
                                                <option value="solid">Solid</option>
                                                <option value="regular">Regular</option>
                                                <option value="brands">Brands</option>
                                            </select>
                                            <input data-shop-fa-label placeholder="可选辅助说明">
                                        </div>
                                        <div class="shop-fa-picker-grid" data-shop-fa-grid></div>
                                        <div class="shop-fa-picker-actions">
                                            <button type="button" data-shop-fa-custom>插入自定义图标</button>
                                        </div>
                                    </div>
                                `;
                                document.body.appendChild(overlay);

                                const search = overlay.querySelector('[data-shop-fa-search]');
                                const style = overlay.querySelector('[data-shop-fa-style]');
                                const label = overlay.querySelector('[data-shop-fa-label]');
                                const grid = overlay.querySelector('[data-shop-fa-grid]');
                                const close = () => overlay.remove();
                                const choose = (icon, iconStyle = style.value) => {
                                    const shortcode = fontAwesomeShortcode(icon, iconStyle, label.value);

                                    if (shortcode) {
                                        onSelect(shortcode);
                                    }

                                    close();
                                };
                                const renderGrid = () => {
                                    const query = String(search.value || '').trim().toLowerCase();
                                    const options = fontAwesomeIconOptions
                                        .filter(([icon, text]) => ! query || icon.includes(query) || text.toLowerCase().includes(query))
                                        .slice(0, 30);

                                    grid.innerHTML = options.map(([icon, text, iconStyle]) => {
                                        const family = { solid: 'fa-solid', regular: 'fa-regular', brands: 'fa-brands' }[iconStyle] || 'fa-solid';

                                        return `<button type="button" data-shop-fa-icon="${icon}" data-shop-fa-icon-style="${iconStyle}"><i class="${family} fa-${icon} fa-fw" aria-hidden="true"></i><span>${text}</span><small>${icon}</small></button>`;
                                    }).join('') || '<p>没有匹配的常用图标，可使用自定义插入。</p>';
                                };

                                overlay.addEventListener('click', (event) => {
                                    if (event.target === overlay || event.target.closest('[data-shop-fa-close]')) {
                                        close();
                                        return;
                                    }

                                    const option = event.target.closest('[data-shop-fa-icon]');

                                    if (option) {
                                        choose(option.dataset.shopFaIcon, option.dataset.shopFaIconStyle);
                                    }

                                    if (event.target.closest('[data-shop-fa-custom]')) {
                                        choose(search.value, style.value);
                                    }
                                });
                                search.addEventListener('input', renderGrid);
                                overlay.addEventListener('keydown', (event) => {
                                    if (event.key === 'Escape') {
                                        close();
                                    }
                                });
                                renderGrid();
                                search.focus();
                            };

                            const renderSimpleMarkdownFallback = (markdown) => {
                                const lines = String(markdown ?? '').replace(/\r\n?/g, '\n').split('\n');
                                const blocks = [];
                                let paragraph = [];
                                let list = [];
                                let quote = [];
                                let code = [];
                                let inCode = false;

                                const renderInline = (value) => escapeMarkdownHtml(value)
                                    .replace(/!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/g, '<img src="$2" alt="$1">')
                                    .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
                                    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                                    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                                    .replace(/`([^`]+)`/g, '<code>$1</code>')
                                    .replace(/\[fa:(?:(solid|regular|brands):)?([a-z0-9][a-z0-9-]*)(?:\s+([^\]]{1,80}))?\]/gi, (match) => renderFontAwesomeShortcodes(match));

                                const flushParagraph = () => {
                                    if (paragraph.length) {
                                        blocks.push(`<p>${renderInline(paragraph.join(' '))}</p>`);
                                        paragraph = [];
                                    }
                                };

                                const flushList = () => {
                                    if (list.length) {
                                        blocks.push(`<ul>${list.map((item) => `<li>${renderInline(item)}</li>`).join('')}</ul>`);
                                        list = [];
                                    }
                                };

                                const flushQuote = () => {
                                    if (quote.length) {
                                        blocks.push(`<blockquote>${quote.map((item) => `<p>${renderInline(item)}</p>`).join('')}</blockquote>`);
                                        quote = [];
                                    }
                                };

                                const flushAll = () => {
                                    flushParagraph();
                                    flushList();
                                    flushQuote();
                                };

                                lines.forEach((line) => {
                                    if (line.trim().startsWith('```')) {
                                        if (inCode) {
                                            blocks.push(`<pre><code>${escapeMarkdownHtml(code.join('\n'))}</code></pre>`);
                                            code = [];
                                        } else {
                                            flushAll();
                                        }

                                        inCode = ! inCode;

                                        return;
                                    }

                                    if (inCode) {
                                        code.push(line);

                                        return;
                                    }

                                    const trimmed = line.trim();

                                    if (trimmed === '') {
                                        flushAll();

                                        return;
                                    }

                                    const heading = trimmed.match(/^(#{1,6})\s+(.+)$/);

                                    if (heading) {
                                        flushAll();
                                        const level = Math.min(heading[1].length, 6);
                                        blocks.push(`<h${level}>${renderInline(heading[2])}</h${level}>`);

                                        return;
                                    }

                                    const listItem = trimmed.match(/^[-*+]\s+(.+)$/);

                                    if (listItem) {
                                        flushParagraph();
                                        flushQuote();
                                        list.push(listItem[1]);

                                        return;
                                    }

                                    const quoteLine = trimmed.match(/^>\s?(.+)$/);

                                    if (quoteLine) {
                                        flushParagraph();
                                        flushList();
                                        quote.push(quoteLine[1]);

                                        return;
                                    }

                                    flushList();
                                    flushQuote();
                                    paragraph.push(line);
                                });

                                if (inCode) {
                                    blocks.push(`<pre><code>${escapeMarkdownHtml(code.join('\n'))}</code></pre>`);
                                }

                                flushAll();

                                return blocks.join('\n');
                            };

                            const getMarkdownPreviewTargets = (editor) => {
                                const wrapper = editor?.codemirror?.getWrapperElement();

                                if (! wrapper) {
                                    return [];
                                }

                                const targets = [];
                                const fullPreview = wrapper.lastElementChild?.classList?.contains('editor-preview-active')
                                    ? wrapper.lastElementChild
                                    : wrapper.querySelector('.editor-preview-full.editor-preview-active, .editor-preview.editor-preview-active');
                                const sidePreview = Array.from(wrapper.parentElement?.children ?? [])
                                    .find((element) => element.classList?.contains('editor-preview-side'));

                                if (fullPreview) {
                                    targets.push(fullPreview);
                                }

                                if (sidePreview?.classList?.contains('editor-preview-active-side')) {
                                    targets.push(sidePreview);
                                }

                                return targets;
                            };

                            const renderMarkdownIntoPreview = (editor, preview) => {
                                if (! editor || ! preview) {
                                    return;
                                }

                                const value = editor.value();
                                let rendered = null;

                                try {
                                    if (typeof editor.options?.previewRender === 'function') {
                                        rendered = editor.options.previewRender.call(editor.options, value, preview);
                                    }

                                    if ((rendered === null || rendered === undefined || (rendered === '' && value.trim() !== '')) && typeof editor.markdown === 'function') {
                                        rendered = editor.markdown(value);
                                    }

                                    if ((rendered === null || rendered === undefined || (rendered === '' && value.trim() !== ''))) {
                                        rendered = renderSimpleMarkdownFallback(value);
                                    }

                                    if (rendered !== null && rendered !== undefined) {
                                        preview.innerHTML = renderFontAwesomeShortcodes(rendered);
                                    }
                                } catch (error) {
                                    console.error('Markdown preview rendering failed.', error);

                                    preview.innerHTML = renderSimpleMarkdownFallback(value);
                                }
                            };

                            const renderMarkdownPreview = (editor) => {
                                prepareMarkdownEditorForCustomPreview(editor);

                                const targets = getMarkdownPreviewTargets(editor);

                                targets.forEach((preview) => {
                                    renderMarkdownIntoPreview(editor, preview);
                                });
                            };

                            const refreshMarkdownMode = (editor, toolbar) => {
                                window.setTimeout(() => {
                                    toolbar?.classList?.remove('disabled-for-preview');
                                    editor.codemirror.refresh();
                                    renderMarkdownPreview(editor);

                                    toolbar?.querySelectorAll('.shop-md-tool-btn').forEach((button) => {
                                        button.classList.toggle('active', button.classList.contains('shop-md-tool-edit') && ! editor.isPreviewActive() && ! editor.isSideBySideActive());
                                        button.classList.toggle('active', button.classList.contains('shop-md-tool-preview') && editor.isPreviewActive());
                                        button.classList.toggle('active', button.classList.contains('shop-md-tool-side-by-side') && editor.isSideBySideActive());
                                    });
                                }, 20);
                            };

                            const setMarkdownMode = (editor, mode) => {
                                prepareMarkdownEditorForCustomPreview(editor);

                                const wrapper = editor?.codemirror?.getWrapperElement();
                                const container = wrapper?.parentElement;
                                const toolbar = editor?.toolbar_div;
                                const fullPreview = ensureFullMarkdownPreview(editor);
                                const sidePreview = ensureSideMarkdownPreview(editor);

                                if (! wrapper || ! container) {
                                    return;
                                }

                                toolbar?.classList?.remove('disabled-for-preview');

                                if (mode === 'edit') {
                                    fullPreview?.classList?.remove('editor-preview-active');
                                    sidePreview?.classList?.remove('editor-preview-active-side');
                                    wrapper.classList.remove('CodeMirror-sided');
                                    container.classList.remove('sided--no-fullscreen');
                                    editor.codemirror.refresh();

                                    return;
                                }

                                if (mode === 'preview') {
                                    sidePreview?.classList?.remove('editor-preview-active-side');
                                    wrapper.classList.remove('CodeMirror-sided');
                                    container.classList.remove('sided--no-fullscreen');
                                    fullPreview?.classList?.add('editor-preview-active');
                                    renderMarkdownIntoPreview(editor, fullPreview);

                                    return;
                                }

                                fullPreview?.classList?.remove('editor-preview-active');
                                wrapper.classList.add('CodeMirror-sided');
                                container.classList.add('sided--no-fullscreen');
                                sidePreview?.classList?.add('editor-preview-active-side');
                                renderMarkdownIntoPreview(editor, sidePreview);
                                editor.codemirror.refresh();
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

                                        prepareMarkdownEditorForCustomPreview(editor);

                                        toolbar.dataset.shopEnhanced = '1';

                                        const tabs = document.createElement('div');
                                        tabs.className = 'shop-md-tabs';
                                        toolbar.appendChild(tabs);

                                        editor.codemirror.on('change', () => renderMarkdownPreview(editor));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'edit',
                                            '编辑',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M4 14.5V17h2.5L15 8.5 12.5 6 4 14.5Zm13.1-8.6a1.2 1.2 0 0 0 0-1.7l-1.3-1.3a1.2 1.2 0 0 0-1.7 0l-1 1 2.5 2.5 1.1-1.1Z"/></svg>',
                                            () => {
                                                setMarkdownMode(editor, 'edit');
                                                refreshMarkdownMode(editor, toolbar);
                                            },
                                        ));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'preview',
                                            '预览',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M10 4.5c-3.9 0-7 3.1-8.4 5.5 1.4 2.4 4.5 5.5 8.4 5.5s7-3.1 8.4-5.5C17 7.6 13.9 4.5 10 4.5Zm0 9.2A3.7 3.7 0 1 1 10 6.3a3.7 3.7 0 0 1 0 7.4Zm0-1.5a2.2 2.2 0 1 0 0-4.4 2.2 2.2 0 0 0 0 4.4Z"/></svg>',
                                            () => {
                                                setMarkdownMode(editor, 'preview');
                                                refreshMarkdownMode(editor, toolbar);
                                            },
                                        ));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'side-by-side',
                                            '分屏预览',
                                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M3.5 3A1.5 1.5 0 0 0 2 4.5v11A1.5 1.5 0 0 0 3.5 17h13a1.5 1.5 0 0 0 1.5-1.5v-11A1.5 1.5 0 0 0 16.5 3h-13ZM4 5h5.25v10H4V5Zm6.75 0H16v10h-5.25V5Z"/></svg>',
                                            () => {
                                                setMarkdownMode(editor, 'side-by-side');
                                                refreshMarkdownMode(editor, toolbar);
                                            },
                                        ));

                                        tabs.appendChild(makeMarkdownToolButton(
                                            'font-awesome',
                                            '图标',
                                            '<i class="fa-solid fa-icons" aria-hidden="true"></i>',
                                            () => openFontAwesomePicker((shortcode) => {
                                                insertIntoMarkdownEditor(editor, shortcode);
                                                refreshMarkdownMode(editor, toolbar);
                                            }),
                                        ));

                                        refreshMarkdownMode(editor, toolbar);
                                    });
                                });
                            };

                            safeAdminHandler('admin-markdown-editor', () => {
                                const enhance = safeAdminHandler('admin-markdown-editor', enhanceMarkdownEditors);
                                const markdownObserver = new MutationObserver(enhance);

                                document.addEventListener('DOMContentLoaded', safeAdminHandler('admin-markdown-editor', () => {
                                    enhance();
                                    if (document.body) markdownObserver.observe(document.body, { childList: true, subtree: true });
                                }));
                                document.addEventListener('livewire:navigated', enhance);
                            })();
                        })();
                    </script>
                    __SHOPWEB_ADMIN_MODULE_TAG__
                    HTML,
                    ),
                ),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): HtmlString => new HtmlString(
                    '<a class="shop-admin-front-link" href="'.e(\App\Support\Url::route('home')).'" title="返回前台" aria-label="返回前台">'.
                    \Filament\Support\generate_icon_html(Heroicon::OutlinedArrowTopRightOnSquare)?->toHtml().
                    '<span>返回前台</span>'.
                    '</a>'
                ),
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

    private function adminModuleTag(): string
    {
        try {
            return '<script type="module" src="'.e(Vite::asset('resources/js/admin.js')).'"></script>';
        } catch (Throwable) {
            return '<!-- ShopWeb admin enhancements unavailable: Vite asset missing. -->';
        }
    }

    private function adminThemeStyle(): string
    {
        $primary = '#2D9CDB';
        $accent = '#F5A9B8';
        $canvas = '#FFF7FB';

        $settings = $this->siteSettings();
        $primary = $this->validColor($settings?->primary_color, $primary);
        $accent = $this->validColor($settings?->accent_color, $accent);
        $canvas = $this->validColor($settings?->background_color, $canvas, allowLight: true);

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

                .shopweb-admin-group-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 1.25rem;
                    height: 1.25rem;
                    margin-inline-start: auto;
                    margin-inline-end: .25rem;
                    border-radius: 999px;
                    background: #dc2626;
                    color: #fff;
                    font-size: .6875rem;
                    font-weight: 700;
                    line-height: 1;
                    box-shadow: 0 0 0 2px color-mix(in srgb, var(--wp-admin-canvas), #fff 35%);
                }

                .dark .shopweb-admin-group-badge {
                    box-shadow: 0 0 0 2px #111827;
                }

                .shopweb-admin-item-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 1.25rem;
                    height: 1.25rem;
                    margin-inline-start: auto;
                    padding-inline: .35rem;
                    border-radius: 999px;
                    background: #dc2626;
                    color: #fff;
                    font-size: .6875rem;
                    font-weight: 700;
                    line-height: 1;
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
        $request = request();
        $key = 'shopweb.site_settings';

        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $settings = app(StorefrontCache::class)->settings();
        $request->attributes->set($key, $settings);

        return $settings;
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
