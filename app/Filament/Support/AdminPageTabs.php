<?php

namespace App\Filament\Support;

use Filament\Actions\Action;

class AdminPageTabs
{
    /**
     * @param  array<string, array{label: string, url: string}>  $tabs
     * @return array<int, Action>
     */
    public static function actions(array $tabs, string $active): array
    {
        $actions = [];

        foreach ($tabs as $name => $tab) {
            $isActive = $name === $active;

            $actions[] = Action::make('tab_'.$name)
                ->label($tab['label'])
                ->url($isActive ? null : $tab['url'])
                ->color($isActive ? 'primary' : 'gray')
                ->disabled($isActive);
        }

        return $actions;
    }
}
