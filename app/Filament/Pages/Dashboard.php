<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = '主页';

    protected static ?string $navigationLabel = '主页';

    protected static ?int $navigationSort = -100;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
