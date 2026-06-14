<?php

namespace App\Filament\Resources\NavigationMenuItemResource\Pages;

use App\Filament\Resources\NavigationMenuItemResource;
use App\Models\NavigationMenuItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ListNavigationMenuItems extends ListRecords
{
    protected static string $resource = NavigationMenuItemResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.navigation-menu-item-resource.tree-manager')
                ->viewData(fn ($livewire): array => ['treePage' => $livewire]),
            ...parent::content($schema)->getComponents(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNavigationMenuTree(): array
    {
        return collect(NavigationMenuItem::placementOptions())
            ->mapWithKeys(fn (string $label, string $placement): array => [
                $placement => [
                    'label' => $label,
                    'items' => NavigationMenuItem::query()
                        ->where('placement', $placement)
                        ->whereNull('parent_id')
                        ->with('childrenRecursive')
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->get(),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveNavigationMenuTree(string $placement, array $items): void
    {
        if (! array_key_exists($placement, NavigationMenuItem::placementOptions())) {
            return;
        }

        DB::transaction(function () use ($placement, $items): void {
            $this->persistTreeItems($placement, $items);
        });

        $this->dispatch('navigation-menu-tree-saved');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistTreeItems(string $placement, array $items, ?int $parentId = null): void
    {
        foreach (array_values($items) as $index => $item) {
            $id = (int) ($item['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            NavigationMenuItem::query()
                ->whereKey($id)
                ->where('placement', $placement)
                ->update([
                    'parent_id' => $parentId,
                    'sort_order' => ($index + 1) * 10,
                ]);

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];

            $this->persistTreeItems($placement, $children, $id);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新增菜单项'),
        ];
    }
}
