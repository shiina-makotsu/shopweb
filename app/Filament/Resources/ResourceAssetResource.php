<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\ResourceAssetResource\Pages\CreateResourceAsset;
use App\Filament\Resources\ResourceAssetResource\Pages\EditResourceAsset;
use App\Filament\Resources\ResourceAssetResource\Pages\ListResourceAssets;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use function Filament\Support\original_request;

class ResourceAssetResource extends MediaAssetResource
{
    protected static ?string $navigationLabel = '资源库';
    protected static ?string $modelLabel = '资源文件';
    protected static ?string $pluralModelLabel = '资源库';
    protected static string $permissionArea = 'resources';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;
    protected static ?int $navigationSort = 25;

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*'))
                ->sort(static::getNavigationSort())
                ->url(static::getUrl()),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceAssets::route('/'),
            'create' => CreateResourceAsset::route('/create'),
            'edit' => EditResourceAsset::route('/{record}/edit'),
        ];
    }
}
