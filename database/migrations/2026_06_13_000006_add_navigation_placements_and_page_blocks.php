<?php

use App\Models\NavigationMenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('navigation_menu_items', 'placement')) {
            Schema::table('navigation_menu_items', function (Blueprint $table): void {
                $table->string('placement')->default(NavigationMenuItem::PLACEMENT_TOP_NAV)->index()->after('parent_id');
            });
        }

        if (! Schema::hasColumn('pages', 'blocks')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->json('blocks')->nullable()->after('body');
            });
        }

        if (DB::table('navigation_menu_items')->count() === 0) {
            $now = now();
            $items = [
                ['label' => '首页', 'route_name' => 'home', 'sort_order' => 10],
                ['label' => '全部商品', 'route_name' => 'products.index', 'sort_order' => 20],
                ['label' => 'AI', 'route_name' => 'ai-image.index', 'sort_order' => 30],
                ['label' => '友情链接', 'route_name' => 'friend-links.index', 'sort_order' => 40],
                ['label' => '论坛', 'route_name' => 'forum.index', 'sort_order' => 50],
                ['label' => '物流查询', 'route_name' => 'shipments.show', 'sort_order' => 60],
                ['label' => '客服会话', 'route_name' => 'support.index', 'sort_order' => 70],
                ['label' => '客服工单', 'route_name' => 'support.demands', 'sort_order' => 80],
                ['label' => '订单查询', 'route_name' => 'orders.index', 'sort_order' => 90],
            ];

            DB::table('navigation_menu_items')->insert(array_map(fn (array $item): array => [
                ...$item,
                'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
                'is_active' => true,
                'opens_new_tab' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items));

            $homeMenuId = DB::table('navigation_menu_items')
                ->where('placement', NavigationMenuItem::PLACEMENT_TOP_NAV)
                ->where('route_name', 'home')
                ->value('id');

            if ($homeMenuId) {
                DB::table('navigation_menu_items')->insert([
                    'parent_id' => $homeMenuId,
                    'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
                    'label' => '标签',
                    'route_name' => 'tags.index',
                    'sort_order' => 10,
                    'is_active' => true,
                    'opens_new_tab' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $homeMenuId = DB::table('navigation_menu_items')
            ->where('placement', NavigationMenuItem::PLACEMENT_TOP_NAV)
            ->where('route_name', 'home')
            ->value('id');

        if ($homeMenuId && ! DB::table('navigation_menu_items')
            ->where('placement', NavigationMenuItem::PLACEMENT_TOP_NAV)
            ->where('parent_id', $homeMenuId)
            ->where('route_name', 'tags.index')
            ->exists()) {
            DB::table('navigation_menu_items')->insert([
                'parent_id' => $homeMenuId,
                'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
                'label' => '标签',
                'route_name' => 'tags.index',
                'sort_order' => 10,
                'is_active' => true,
                'opens_new_tab' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pages', 'blocks')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->dropColumn('blocks');
            });
        }

        if (Schema::hasColumn('navigation_menu_items', 'placement')) {
            Schema::table('navigation_menu_items', function (Blueprint $table): void {
                $table->dropColumn('placement');
            });
        }
    }
};
