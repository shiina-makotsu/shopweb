<?php

namespace App\Support;

use App\Models\NavigationMenuItem;
use App\Models\Page;

class PageMenuPublication
{
    /**
     * @var array<int, string>
     */
    private const FORM_KEYS = [
        'menu_placement',
        'menu_parent_id',
        'menu_label',
        'menu_sort_order',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extract(array &$data): array
    {
        $menu = [
            'placement' => $data['menu_placement'] ?? 'none',
            'parent_id' => $data['menu_parent_id'] ?? null,
            'label' => $data['menu_label'] ?? null,
            'sort_order' => $data['menu_sort_order'] ?? null,
        ];

        foreach (self::FORM_KEYS as $key) {
            unset($data[$key]);
        }

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Page $page, array $data): array
    {
        $menu = self::findForPage($page);

        return [
            ...$data,
            'menu_placement' => $menu?->placement ?? 'none',
            'menu_parent_id' => $menu?->parent_id,
            'menu_label' => $menu?->label,
            'menu_sort_order' => $menu?->sort_order ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $menu
     */
    public static function sync(Page $page, array $menu, ?string $oldSlug = null): void
    {
        $placement = $menu['placement'] ?? 'none';
        $existing = self::findForPage($page, $oldSlug);

        if ($placement === 'none') {
            $existing?->delete();

            return;
        }

        if (! in_array($placement, array_keys(NavigationMenuItem::placementOptions()), true)) {
            return;
        }

        if (! $page->is_published || ! $page->newQuery()->whereKey($page->getKey())->menuable()->exists()) {
            $existing?->update(['is_active' => false]);

            return;
        }

        $data = [
            'placement' => $placement,
            'parent_id' => filled($menu['parent_id'] ?? null) ? (int) $menu['parent_id'] : null,
            'label' => filled($menu['label'] ?? null) ? (string) $menu['label'] : $page->title,
            'route_name' => 'pages.show',
            'route_parameters' => ['page' => $page->slug],
            'url' => null,
            'sort_order' => filled($menu['sort_order'] ?? null) ? (int) $menu['sort_order'] : 0,
            'is_active' => true,
            'opens_new_tab' => false,
        ];

        if ($existing) {
            $existing->update($data);

            return;
        }

        NavigationMenuItem::query()->create($data);
    }

    public static function findForPage(Page $page, ?string $oldSlug = null): ?NavigationMenuItem
    {
        $slugs = array_values(array_filter(array_unique([$page->slug, $oldSlug])));

        return NavigationMenuItem::query()
            ->where('route_name', 'pages.show')
            ->get()
            ->first(fn (NavigationMenuItem $item): bool => in_array($item->route_parameters['page'] ?? null, $slugs, true));
    }
}
