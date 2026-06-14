<?php

use App\Models\NavigationMenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('navigation_menu_items')) {
            return;
        }

        if (! Schema::hasColumn('navigation_menu_items', 'placement')) {
            return;
        }

        $now = now();
        $existingPageSlugs = DB::table('navigation_menu_items')
            ->where('route_name', 'pages.show')
            ->get(['route_parameters'])
            ->map(function ($item): ?string {
                $parameters = json_decode((string) $item->route_parameters, true);

                return is_array($parameters) ? ($parameters['page'] ?? null) : null;
            })
            ->filter()
            ->values()
            ->all();

        DB::table('pages')
            ->where('is_published', true)
            ->where('slug', '!=', '404')
            ->where(fn ($query) => $query->whereNull('template')->orWhere('template', '!=', 'not_found'))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['title', 'slug', 'sort_order'])
            ->each(function ($page) use (&$existingPageSlugs, $now): void {
                if (in_array($page->slug, $existingPageSlugs, true)) {
                    return;
                }

                DB::table('navigation_menu_items')->insert([
                    'parent_id' => null,
                    'placement' => NavigationMenuItem::PLACEMENT_HOME_INFO,
                    'label' => $page->title,
                    'url' => null,
                    'route_name' => 'pages.show',
                    'route_parameters' => json_encode(['page' => $page->slug], JSON_UNESCAPED_UNICODE),
                    'sort_order' => (int) $page->sort_order,
                    'is_active' => true,
                    'opens_new_tab' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $existingPageSlugs[] = $page->slug;
            });
    }

    public function down(): void
    {
        //
    }
};
