<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

class CardPreviewTemplate
{
    /**
     * @param  array{
     *     key: string,
     *     title: string,
     *     description?: string,
     *     badge?: string,
     *     cards: string,
     *     settingsSelector: string,
     *     legacyRootAttributes?: string,
     *     legacyGridAttributes?: string,
     *     legacySaveAttributes?: string,
     *     enableSorting?: bool,
     *     originalOrder?: string,
     *     sortFieldLabel?: string,
     *     saveLabel?: string,
     *     minCardWidth?: string
     * } $config
     */
    public static function render(array $config): HtmlString
    {
        $key = static::normalizeKey((string) $config['key']);
        $title = e((string) $config['title']);
        $description = e((string) ($config['description'] ?? ''));
        $badge = e((string) ($config['badge'] ?? ''));
        $cards = (string) $config['cards'];
        $settingsSelector = (string) $config['settingsSelector'];
        $legacyRootAttributes = trim((string) ($config['legacyRootAttributes'] ?? ''));
        $legacyGridAttributes = trim((string) ($config['legacyGridAttributes'] ?? ''));
        $legacySaveAttributes = trim((string) ($config['legacySaveAttributes'] ?? ''));
        $enableSorting = (bool) ($config['enableSorting'] ?? false);
        $originalOrder = e((string) ($config['originalOrder'] ?? ''));
        $sortFieldLabel = (string) ($config['sortFieldLabel'] ?? '排序');
        $saveLabel = e((string) ($config['saveLabel'] ?? '保存排序'));
        $minCardWidth = e((string) ($config['minCardWidth'] ?? '230px'));
        $descriptionHtml = $description === '' ? '' : '<span class="shop-admin-preview-muted" style="color:#64748b;font-size:12px;">'.$description.'</span>';
        $badgeHtml = $badge === '' ? '' : '<span class="shop-admin-preview-badge" style="border-radius:999px;background:#eff8ff;color:#0369a1;padding:3px 9px;font-size:12px;">'.$badge.'</span>';
        $saveHtml = '';

        if ($enableSorting) {
            $saveHtml = <<<HTML
                <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                    <button
                        type="button"
                        data-card-preview-save-order
                        {$legacySaveAttributes}
                        disabled
                        class="shop-admin-preview-save"
                    >{$saveLabel}</button>
                </div>
            HTML;
        }

        $script = static::script($key, $settingsSelector, $enableSorting, $sortFieldLabel);

        return new HtmlString(<<<HTML
            <div
                data-card-preview-root="{$key}"
                data-card-preview-original-order="{$originalOrder}"
                {$legacyRootAttributes}
                class="shop-admin-preview-panel"
                style="border:1px solid #cbd5e1;border-radius:18px;background:#fff;padding:16px;box-shadow:0 10px 34px rgba(15,23,42,.08);"
            >
                <div class="shop-admin-preview-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;">
                    <div>
                        <strong class="shop-admin-preview-title" style="display:block;color:#0f172a;font-size:16px;">{$title}</strong>
                        {$descriptionHtml}
                    </div>
                    {$badgeHtml}
                </div>
                <div
                    data-card-preview-grid
                    {$legacyGridAttributes}
                    class="shop-admin-preview-grid"
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax({$minCardWidth},1fr));gap:12px;"
                >{$cards}</div>
                {$saveHtml}
            </div>
            {$script}
        HTML);
    }

    /**
     * @param  array{
     *     index: int,
     *     title: string,
     *     subtitle?: string,
     *     image?: string,
     *     metrics?: array<int, array{label: string, value: string, class?: string}>,
     *     footer?: string,
     *     legacyAttributes?: string,
     *     isPlaceholder?: bool,
     *     isNew?: bool,
     *     isVirtual?: bool,
     *     draggable?: bool,
     *     imageLayout?: bool
     * } $config
     */
    public static function card(array $config): string
    {
        $index = (int) $config['index'];
        $title = e((string) $config['title']);
        $subtitle = e((string) ($config['subtitle'] ?? ''));
        $image = (string) ($config['image'] ?? '');
        $footer = (string) ($config['footer'] ?? '');
        $legacyAttributes = trim((string) ($config['legacyAttributes'] ?? ''));
        $isPlaceholder = (bool) ($config['isPlaceholder'] ?? false);
        $isNew = (bool) ($config['isNew'] ?? false);
        $isVirtual = (bool) ($config['isVirtual'] ?? false);
        $draggable = (bool) ($config['draggable'] ?? false);
        $imageLayout = (bool) ($config['imageLayout'] ?? false);
        $draggableAttr = $draggable ? 'true' : 'false';
        $draggableClass = $draggable ? 'is-draggable' : '';
        $placeholder = $isPlaceholder ? '1' : '0';
        $new = $isNew ? '1' : '0';
        $virtual = $isVirtual ? '1' : '0';
        $cursor = $draggable ? 'grab' : 'pointer';
        $metrics = collect($config['metrics'] ?? [])
            ->map(function (array $metric): string {
                $label = e((string) ($metric['label'] ?? ''));
                $value = e((string) ($metric['value'] ?? ''));
                $class = e((string) ($metric['class'] ?? ''));

                return <<<HTML
                    <span class="shop-admin-preview-metric" style="border-radius:10px;background:rgba(255,255,255,.72);padding:7px;">
                        <span class="shop-admin-preview-metric-label" style="display:block;color:#64748b;font-size:12px;">{$label}</span>
                        <span class="{$class}" style="font-weight:700;color:#be123c;">{$value}</span>
                    </span>
                HTML;
            })
            ->implode('');
        $metricsHtml = $metrics === '' ? '' : '<span style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">'.$metrics.'</span>';
        $subtitleHtml = $subtitle === '' ? '' : '<span class="shop-admin-preview-card-muted" style="color:#64748b;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'.$subtitle.'</span>';
        $body = <<<HTML
            <span style="display:grid;gap:7px;min-width:0;">
                <strong class="shop-admin-preview-card-title" style="font-size:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{$title}</strong>
                {$subtitleHtml}
                {$metricsHtml}
                {$footer}
            </span>
        HTML;
        $content = $imageLayout
            ? $image.$body
            : $body;
        $layoutStyle = $imageLayout ? 'grid-template-columns:64px 1fr;align-items:center;' : '';

        return <<<HTML
            <button
                type="button"
                draggable="{$draggableAttr}"
                data-card-preview-card="{$index}"
                data-card-preview-placeholder="{$placeholder}"
                data-card-preview-new="{$new}"
                data-card-preview-virtual="{$virtual}"
                {$legacyAttributes}
                class="shop-admin-preview-card {$draggableClass}"
                style="display:grid;{$layoutStyle}gap:12px;border:1px solid #cbd5e1;border-radius:14px;background:linear-gradient(135deg,#f8fbff,#fff1f6);padding:12px 14px;color:#0f172a;box-shadow:0 8px 24px rgba(15,23,42,.08);text-align:left;cursor:{$cursor};"
            >{$content}</button>
        HTML;
    }

    public static function image(?string $url, string $alt = '图片'): string
    {
        if (filled($url)) {
            $escapedUrl = e((string) $url);
            $escapedAlt = e($alt);

            return '<img src="'.$escapedUrl.'" alt="'.$escapedAlt.'" class="shop-admin-preview-image" style="width:64px;height:64px;object-fit:cover;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;">';
        }

        return '<div class="shop-admin-preview-image-placeholder" style="display:grid;width:64px;height:64px;place-items:center;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;color:#94a3b8;font-size:12px;">'.e($alt).'</div>';
    }

    private static function script(string $key, string $settingsSelector, bool $enableSorting, string $sortFieldLabel): string
    {
        $encodedKey = json_encode($key, JSON_THROW_ON_ERROR);
        $encodedSelector = json_encode($settingsSelector, JSON_THROW_ON_ERROR);
        $encodedSorting = $enableSorting ? 'true' : 'false';
        $encodedSortFieldLabel = json_encode($sortFieldLabel, JSON_THROW_ON_ERROR);

        return <<<HTML
            <script>
                (() => {
                    const key = {$encodedKey};
                    window.shopwebCardPreviewTemplateBound ||= {};
                    if (window.shopwebCardPreviewTemplateBound[key]) return;
                    window.shopwebCardPreviewTemplateBound[key] = true;

                    const settingsSelector = {$encodedSelector};
                    const enableSorting = {$encodedSorting};
                    const sortFieldLabel = {$encodedSortFieldLabel};
                    const rootSelector = '[data-card-preview-root="' + CSS.escape(key) + '"]';
                    const settingItemAttribute = 'data-card-preview-setting-item-' + key;
                    const initializedAttribute = 'data-card-preview-initialized-' + key;
                    const styledAttribute = 'data-card-preview-styled-' + key;
                    const visibleAttribute = 'data-card-preview-visible-' + key;
                    const dragState = { card: null };
                    const scrollState = { active: false, x: 0, y: 0, timer: null };
                    let refreshTimer = null;

                    window.shopwebCardPreviewTemplateState ||= {};
                    window.shopwebCardPreviewTemplateState[key] ||= { open: [] };
                    const state = window.shopwebCardPreviewTemplateState[key];

                    const roots = () => Array.from(document.querySelectorAll(rootSelector));
                    const scheduleRefresh = () => {
                        window.clearTimeout(refreshTimer);
                        refreshTimer = window.setTimeout(() => initializeRoots({ restoreVisibility: false, updateContainer: false }), 120);
                    };
                    const scheduleRestore = () => {
                        window.clearTimeout(refreshTimer);
                        refreshTimer = window.setTimeout(() => initializeRoots({ restoreVisibility: true, updateContainer: true }), 20);
                    };
                    const openIndexes = () => new Set((state.open || []).map((value) => Number.parseInt(value, 10)).filter((value) => Number.isFinite(value)));
                    const setOpen = (index, open) => {
                        const values = openIndexes();

                        if (open) {
                            values.add(index);
                        } else {
                            values.delete(index);
                        }

                        state.open = Array.from(values).sort((a, b) => a - b);
                    };
                    const grid = (root) => root?.querySelector('[data-card-preview-grid]');
                    const cards = (root) => Array.from(grid(root)?.querySelectorAll('[data-card-preview-card]') || []);
                    const originalOrder = (root) => (root?.dataset.cardPreviewOriginalOrder || '').split(',').filter(Boolean);
                    const currentOrder = (root) => cards(root)
                        .filter((card) => card.dataset.cardPreviewPlaceholder !== '1')
                        .map((card) => card.dataset.cardPreviewCard || '');
                    const sectionFor = (root) => root?.closest('section, form') || document;
                    const settingsFor = (root) => {
                        const scopes = [
                            root?.closest('[data-field-wrapper]'),
                            root?.closest('section'),
                            root?.closest('form'),
                            document,
                        ].filter(Boolean);

                        for (const scope of scopes) {
                            const settings = scope.querySelector(settingsSelector);

                            if (settings) return settings;
                        }

                        return null;
                    };
                    const rememberScroll = () => {
                        scrollState.active = true;
                        scrollState.x = window.scrollX;
                        scrollState.y = window.scrollY;
                        window.clearTimeout(scrollState.timer);
                        scrollState.timer = window.setTimeout(() => {
                            scrollState.active = false;
                        }, 1500);
                    };
                    const restoreScroll = () => {
                        if (! scrollState.active) return;

                        window.requestAnimationFrame(() => {
                            window.scrollTo(scrollState.x, scrollState.y);
                            window.requestAnimationFrame(() => window.scrollTo(scrollState.x, scrollState.y));
                        });
                    };
                    const settingsInteractionTarget = (event) => {
                        const target = event.target instanceof Element ? event.target : event.target?.parentElement;

                        if (! target?.closest?.(settingsSelector)) return null;
                        if (target.closest('[data-card-preview-card], [data-card-preview-save-order]')) return null;

                        return target;
                    };
                    const fieldByLabel = (item, label) => {
                        const labels = Array.from(item.querySelectorAll('label'));
                        const matched = labels.find((node) => node.textContent.trim().includes(label));
                        const id = matched?.getAttribute('for');

                        return id ? item.querySelector('#' + CSS.escape(id)) : null;
                    };
                    const applySettingBlockStyle = (item) => {
                        if (! item || item.getAttribute(styledAttribute) === '1') return;

                        const dark = document.documentElement.classList.contains('dark');
                        item.classList.add('shop-admin-preview-setting');
                        item.setAttribute(styledAttribute, '1');
                        item.style.border = '1px solid ' + (dark ? '#334155' : '#cbd5e1');
                        item.style.borderRadius = '14px';
                        item.style.background = dark ? '#111827' : '#ffffff';
                        item.style.boxShadow = dark ? '0 8px 24px rgba(0,0,0,.24)' : '0 8px 24px rgba(15,23,42,.08)';
                        item.style.padding = '14px';
                        item.style.marginTop = '12px';
                    };
                    const markItemVisibility = (item, visible) => {
                        if (! item) return;

                        item.style.display = visible ? '' : 'none';
                        item.setAttribute(visibleAttribute, visible ? '1' : '0');
                    };
                    const topLevelSettingItems = (settings) => Array.from(settings?.querySelectorAll('.fi-fo-repeater-item') || [])
                        .filter((item) => {
                            const parentItem = item.parentElement?.closest('.fi-fo-repeater-item');

                            return ! parentItem || ! settings.contains(parentItem);
                        });
                    const topLevelAddButton = (settings) => Array.from(settings?.querySelectorAll('.fi-fo-repeater-add button') || [])
                        .find((button) => {
                            const parentItem = button.parentElement?.closest('.fi-fo-repeater-item');

                            return ! parentItem || ! settings.contains(parentItem);
                        });
                    const settingItems = (root, options = {}) => {
                        const restoreVisibility = Boolean(options.restoreVisibility);
                        const settings = settingsFor(root);
                        const items = topLevelSettingItems(settings)
                            .map((item, index) => {
                                item.setAttribute(settingItemAttribute, String(index));
                                applySettingBlockStyle(item);

                                if (item.getAttribute(initializedAttribute) !== '1') {
                                    item.setAttribute(initializedAttribute, '1');
                                    markItemVisibility(item, openIndexes().has(index));
                                } else if (restoreVisibility) {
                                    markItemVisibility(item, openIndexes().has(index));
                                }

                                return item;
                            });

                        return items.length > 0
                            ? items
                            : Array.from(sectionFor(root).querySelectorAll('[' + settingItemAttribute + ']'))
                                .map((item) => {
                                    applySettingBlockStyle(item);

                                    return item;
                                })
                                .sort((a, b) => Number.parseInt(a.getAttribute(settingItemAttribute) || '0', 10) - Number.parseInt(b.getAttribute(settingItemAttribute) || '0', 10));
                    };
                    const refreshSettingsVisibility = (root, items) => {
                        const settings = settingsFor(root);
                        const anyOpen = items.some((item) => item.style.display !== 'none');

                        if (settings) {
                            settings.style.display = anyOpen ? '' : 'none';
                        }
                    };
                    const restoreSettingsVisibility = (root, items) => {
                        const settings = settingsFor(root);
                        const open = openIndexes();

                        items.forEach((item, index) => {
                            markItemVisibility(item, open.has(index));
                        });

                        if (settings) {
                            settings.style.display = open.size > 0 ? '' : 'none';
                        }
                    };
                    const showItem = (root, item) => {
                        if (! item) return;

                        const items = settingItems(root);
                        const index = Number.parseInt(item.getAttribute(settingItemAttribute) || '0', 10);

                        setOpen(index, true);
                        markItemVisibility(item, true);
                        refreshSettingsVisibility(root, items);
                    };
                    const orderChanged = (root) => {
                        const original = originalOrder(root);
                        const current = currentOrder(root);

                        return original.length === current.length && current.some((value, index) => value !== original[index]);
                    };
                    const setSaveButtonState = (root) => {
                        const button = root?.querySelector('[data-card-preview-save-order]');
                        const changed = enableSorting && root ? orderChanged(root) : false;

                        if (! button) return;

                        button.disabled = ! changed;
                        button.style.background = changed ? 'linear-gradient(135deg,#bae6fd,#fbcfe8)' : '';
                        button.style.borderColor = changed ? '#7dd3fc' : '';
                        button.style.color = changed ? '#0f172a' : '';
                        button.style.cursor = changed ? 'pointer' : 'not-allowed';
                    };
                    const syncSortInputs = (root) => {
                        const form = root.closest('form');
                        const items = settingItems(root);

                        currentOrder(root).forEach((value, orderIndex) => {
                            const item = items[Number.parseInt(value || '0', 10)];
                            const input = item ? fieldByLabel(item, sortFieldLabel) : null;

                            if (! input) return;

                            input.value = String((orderIndex + 1) * 10);
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                        form?.requestSubmit();
                    };

                    document.addEventListener('dragstart', (event) => {
                        if (! enableSorting) return;

                        const card = event.target.closest('[data-card-preview-card]');
                        const root = card?.closest(rootSelector);

                        if (! card || ! root || card.dataset.cardPreviewPlaceholder === '1') return;

                        dragState.card = card;
                        card.style.opacity = '.55';
                        event.dataTransfer.effectAllowed = 'move';
                    });

                    document.addEventListener('dragend', () => {
                        if (! dragState.card) return;

                        const root = dragState.card.closest(rootSelector);
                        dragState.card.style.opacity = '';
                        dragState.card = null;
                        setSaveButtonState(root);
                    });

                    document.addEventListener('dragover', (event) => {
                        if (! enableSorting || ! dragState.card) return;

                        const target = event.target.closest('[data-card-preview-card]');
                        const root = target?.closest(rootSelector);

                        if (! target || ! root || target === dragState.card || target.dataset.cardPreviewPlaceholder === '1') return;

                        event.preventDefault();
                        const targetGrid = target.closest('[data-card-preview-grid]');
                        const box = target.getBoundingClientRect();
                        const after = event.clientY > box.top + box.height / 2 || event.clientX > box.left + box.width / 2;

                        targetGrid.insertBefore(dragState.card, after ? target.nextSibling : target);
                    });

                    document.addEventListener('click', (event) => {
                        const button = event.target.closest('[data-card-preview-save-order]');
                        const root = button?.closest(rootSelector);

                        if (! button || ! root || button.disabled) return;

                        syncSortInputs(root);
                    });

                    ['click', 'change', 'input'].forEach((eventName) => {
                        document.addEventListener(eventName, (event) => {
                            if (settingsInteractionTarget(event)) {
                                rememberScroll();
                            }
                        }, true);
                    });

                    document.addEventListener('click', (event) => {
                        const card = event.target.closest('[data-card-preview-card]');
                        const root = card?.closest(rootSelector);

                        if (! card || ! root) return;

                        const settings = settingsFor(root);
                        let items = settingItems(root);
                        const index = Number.parseInt(card.dataset.cardPreviewCard || '0', 10);
                        let item = items[index];

                        if (card.dataset.cardPreviewNew === '1' && card.dataset.cardPreviewVirtual === '1') {
                            const addButton = topLevelAddButton(settings);
                            addButton?.click();

                            window.setTimeout(() => {
                                items = settingItems(root, { restoreVisibility: true });
                                showItem(root, items[items.length - 1]);
                            }, 250);

                            return;
                        }

                        if (! item) return;

                        const isOpen = item.style.display !== 'none';
                        markItemVisibility(item, ! isOpen);
                        setOpen(index, ! isOpen);
                        refreshSettingsVisibility(root, items);
                    });

                    const initializeRoots = (options = {}) => roots().forEach((root) => {
                        const items = settingItems(root, options);
                        if (options.restoreVisibility) {
                            restoreSettingsVisibility(root, items);
                        } else if (options.updateContainer !== false) {
                            refreshSettingsVisibility(root, items);
                        }
                        setSaveButtonState(root);
                    });

                    initializeRoots({ restoreVisibility: true });

                    const observer = new MutationObserver((mutations) => {
                        const touchesPreview = mutations.some((mutation) => {
                            const target = mutation.target instanceof Element ? mutation.target : mutation.target?.parentElement;

                            return target?.closest?.(rootSelector);
                        });
                        const touchesSettings = mutations.some((mutation) => {
                            const target = mutation.target instanceof Element ? mutation.target : mutation.target?.parentElement;

                            return target?.closest?.(settingsSelector);
                        });

                        if (touchesPreview) {
                            scheduleRefresh();
                        } else if (touchesSettings) {
                            scheduleRestore();
                            restoreScroll();
                        }
                    });

                    observer.observe(document.body, { childList: true, subtree: true });
                    document.addEventListener('livewire:init', () => scheduleRefresh());
                    document.addEventListener('livewire:navigated', () => scheduleRefresh());
                    document.addEventListener('livewire:update', () => {
                        scheduleRestore();
                        restoreScroll();
                    });
                    document.addEventListener('livewire:updated', () => {
                        scheduleRestore();
                        restoreScroll();
                    });
                    document.addEventListener('livewire:morphed', () => {
                        scheduleRestore();
                        restoreScroll();
                    });
                    window.Livewire?.hook?.('morph.updated', () => {
                        scheduleRestore();
                        restoreScroll();
                    });
                    window.Livewire?.hook?.('commit', ({ succeed }) => succeed(() => {
                        scheduleRestore();
                        restoreScroll();
                    }));
                })();
            </script>
        HTML;
    }

    private static function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', $key) ?: 'card-preview';
    }
}
