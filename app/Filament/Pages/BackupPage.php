<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BackupPage extends Page
{
    protected static ?string $navigationLabel = '数据备份';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;
    protected static ?int $navigationSort = 90;
    protected static ?string $slug = 'backups';
    protected string $view = 'filament.pages.backup';

    public function getTitle(): string
    {
        return '数据备份';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }
}
