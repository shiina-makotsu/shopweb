<?php

namespace App\Filament\Pages;

use App\Support\SystemInfo;
use App\Support\AdminAccess;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SystemInfoPage extends Page
{
    protected static ?string $navigationLabel = '系统信息';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;
    protected static ?int $navigationSort = 70;
    protected static ?string $slug = 'system-info';
    protected string $view = 'filament.pages.system-info';

    public function getTitle(): string
    {
        return '系统信息';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    /**
     * @return array<string, array<int, array{label:string,value:string,status:?bool}>>
     */
    public function sections(): array
    {
        $info = app(SystemInfo::class);

        return [
            '运行环境' => $info->runtime(),
            '数据库' => $info->database(),
            '目录权限' => $info->writablePaths(),
            '安装状态' => $info->installer(),
        ];
    }
}
