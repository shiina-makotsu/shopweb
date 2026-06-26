<?php

namespace App\Support;

use App\Filament\Pages\AdminSearchPage;
use App\Filament\Pages\BackupPage;
use App\Filament\Pages\CacheManagementPage;
use App\Filament\Pages\CurrencySettingsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\GuideAiSettingsPage;
use App\Filament\Pages\MailSettingsPage;
use App\Filament\Pages\NotFoundContentPage;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Pages\ProductDiscountPage;
use App\Filament\Pages\ProfitOverviewPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StoreInfoPage;
use App\Filament\Pages\SupportAiSettingsPage;
use App\Filament\Pages\SystemInfoPage;
use App\Filament\Pages\UserAiPage;
use App\Filament\Resources\AiWorkflowResource;
use App\Models\AdminMenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminMenuRegistry
{
    private bool $syncedDefaults = false;

    /**
     * @return array<int, array{label: string, icon: mixed, sort: int}>
     */
    public function defaultGroups(): array
    {
        return [
            ['label' => '主页', 'icon' => Heroicon::OutlinedHome, 'sort' => 0],
            ['label' => '审批', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'sort' => 5],
            ['label' => '商品', 'icon' => Heroicon::OutlinedShoppingBag, 'sort' => 10],
            ['label' => '目录', 'icon' => Heroicon::OutlinedSquares2x2, 'sort' => 20],
            ['label' => '交易', 'icon' => Heroicon::OutlinedShoppingCart, 'sort' => 30],
            ['label' => '仓库', 'icon' => Heroicon::OutlinedArchiveBox, 'sort' => 40],
            ['label' => '财务', 'icon' => Heroicon::OutlinedBanknotes, 'sort' => 50],
            ['label' => '用户', 'icon' => Heroicon::OutlinedUsers, 'sort' => 60],
            ['label' => 'AI', 'icon' => Heroicon::OutlinedCpuChip, 'sort' => 65],
            ['label' => '客服', 'icon' => Heroicon::OutlinedLifebuoy, 'sort' => 70],
            ['label' => '内容', 'icon' => Heroicon::OutlinedDocumentText, 'sort' => 80],
            ['label' => '论坛', 'icon' => Heroicon::OutlinedChatBubbleLeftRight, 'sort' => 90],
            ['label' => '报告', 'icon' => Heroicon::OutlinedChartBarSquare, 'sort' => 100],
            ['label' => '系统', 'icon' => Heroicon::OutlinedCog6Tooth, 'sort' => 110],
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function pageClasses(): array
    {
        return [
            Dashboard::class,
            AdminSearchPage::class,
            BackupPage::class,
            CacheManagementPage::class,
            CurrencySettingsPage::class,
            GuideAiSettingsPage::class,
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
            UserAiPage::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function resourceClasses(): array
    {
        return collect(File::glob(app_path('Filament/Resources/*Resource.php')) ?: [])
            ->map(fn (string $path): string => 'App\\Filament\\Resources\\'.pathinfo($path, PATHINFO_FILENAME))
            ->filter(fn (string $class): bool => class_exists($class))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, class-string>
     */
    public function navigationClasses(): array
    {
        return array_values(array_unique([
            ...$this->pageClasses(),
            ...$this->resourceClasses(),
        ]));
    }

    public function syncDefaults(): void
    {
        if ($this->syncedDefaults) {
            return;
        }

        if (! $this->tableReady()) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->defaultGroups() as $group) {
                $record = AdminMenuItem::query()->firstOrCreate(
                    ['item_key' => $this->groupKey($group['label'])],
                    [
                        'type' => AdminMenuItem::TYPE_GROUP,
                        'label' => $group['label'],
                        'sort_order' => $group['sort'],
                        'is_active' => true,
                    ],
                );

                if ($group['label'] === '主页' && (! $record->is_active || (int) $record->sort_order !== (int) $group['sort'])) {
                    $record->forceFill([
                        'sort_order' => $group['sort'],
                        'is_active' => true,
                    ])->save();
                }
            }

            foreach ($this->navigationClasses() as $class) {
                if (! $this->shouldRegister($class)) {
                    continue;
                }

                $groupLabel = $this->groupLabelFor($class);
                $group = AdminMenuItem::query()->firstOrCreate(
                    ['item_key' => $this->groupKey($groupLabel)],
                    [
                        'type' => AdminMenuItem::TYPE_GROUP,
                        'label' => $groupLabel,
                        'sort_order' => 999,
                        'is_active' => true,
                    ],
                );

                $item = AdminMenuItem::query()->firstOrNew(['item_key' => $this->classKey($class)]);
                $item->forceFill([
                    'parent_id' => $item->exists ? ($item->parent_id ?: $group->getKey()) : $group->getKey(),
                    'type' => AdminMenuItem::TYPE_ITEM,
                    'label' => $this->stringValue($class::getNavigationLabel()) ?: class_basename($class),
                    'source_class' => $class,
                    'url' => $this->urlFor($class),
                    'sort_order' => $item->exists ? $item->sort_order : (int) ($class::getNavigationSort() ?? 0),
                    'is_active' => true,
                ])->save();

                if ($class === Dashboard::class && $item->parent_id !== $group->getKey()) {
                    $item->forceFill([
                        'parent_id' => $group->getKey(),
                        'sort_order' => (int) ($class::getNavigationSort() ?? -100),
                        'is_active' => true,
                    ])->save();
                }

                $this->moveAiItemToAiGroup($class, $group);
            }
        });

        $this->syncedDefaults = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function tree(): array
    {

        return [
            'admin' => [
                'label' => '后台菜单',
                'items' => $this->tableReady()
                    ? AdminMenuItem::query()
                        ->where('type', AdminMenuItem::TYPE_GROUP)
                        ->whereNull('parent_id')
                        ->with('childrenRecursive')
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->get()
                    : new Collection(),
            ],
        ];
    }

    /**
     * @return array<int, NavigationGroup>
     */
    public function navigationGroups(): array
    {
        $groups = collect($this->defaultGroups())->keyBy('label');

        if ($this->tableReady()) {

            $configured = AdminMenuItem::query()
                ->where('type', AdminMenuItem::TYPE_GROUP)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            if ($configured->isNotEmpty()) {
                return $configured
                    ->map(fn (AdminMenuItem $item): NavigationGroup => $this->makeGroup(
                        $item->label,
                        $groups->get($item->label)['icon'] ?? Heroicon::OutlinedFolder,
                    ))
                    ->all();
            }
        }

        return $groups
            ->sortBy('sort')
            ->map(fn (array $group): NavigationGroup => $this->makeGroup($group['label'], $group['icon']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function browserConfig(): array
    {
        if (! $this->tableReady()) {
            return ['groups' => [], 'items' => []];
        }


        $groups = AdminMenuItem::query()
            ->where('type', AdminMenuItem::TYPE_GROUP)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return [
            'groups' => $groups->map(fn (AdminMenuItem $group): array => [
                'label' => $group->label,
                'sort' => $group->sort_order,
            ])->values()->all(),
            'items' => AdminMenuItem::query()
                ->where('type', AdminMenuItem::TYPE_ITEM)
                ->where('is_active', true)
                ->whereNotNull('parent_id')
                ->with('parent')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (AdminMenuItem $item): array => [
                    'label' => $item->label,
                    'group' => $item->parent?->label,
                    'url' => $item->url,
                    'sort' => $item->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('admin_menu_items');
        } catch (Throwable) {
            return false;
        }
    }

    private function makeGroup(string $label, mixed $icon): NavigationGroup
    {
        return NavigationGroup::make($label)
            ->icon($icon)
            ->collapsible(true)
            ->collapsed();
    }

    private function groupKey(string $label): string
    {
        return 'group:'.$label;
    }

    private function classKey(string $class): string
    {
        return 'class:'.$class;
    }

    private function shouldRegister(string $class): bool
    {
        if ($class === Dashboard::class) {
            return true;
        }

        try {
            return ! method_exists($class, 'shouldRegisterNavigation') || $class::shouldRegisterNavigation();
        } catch (Throwable) {
            return false;
        }
    }

    private function urlFor(string $class): ?string
    {
        try {
            return method_exists($class, 'getUrl') ? $class::getUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value instanceof \UnitEnum) {
            return method_exists($value, 'getLabel') ? $value->getLabel() : $value->name;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function groupLabelFor(string $class): string
    {
        if ($class === Dashboard::class) {
            return '主页';
        }

        return $this->stringValue($class::getNavigationGroup()) ?: '系统';
    }

    private function moveAiItemToAiGroup(string $class, AdminMenuItem $group): void
    {
        if (! in_array($class, [
            SupportAiSettingsPage::class,
            UserAiPage::class,
            GuideAiSettingsPage::class,
            AiWorkflowResource::class,
        ], true)) {
            return;
        }

        AdminMenuItem::query()
            ->where('item_key', $this->classKey($class))
            ->update([
                'parent_id' => $group->getKey(),
                'label' => $this->stringValue($class::getNavigationLabel()) ?: class_basename($class),
                'url' => $this->urlFor($class),
            ]);
    }
}
