<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\InventoryMovementResource\Pages\ListInventoryMovements;
use App\Models\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = InventoryMovement::class;
    protected static string $permissionArea = 'inventory';
    protected static ?string $navigationLabel = '库存流水';
    protected static ?string $modelLabel = '库存流水';
    protected static ?string $pluralModelLabel = '库存流水';
    protected static string|\UnitEnum|null $navigationGroup = '仓库';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;
    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('order.order_number')->label('订单'),
                TextColumn::make('delta')->label('变化')->sortable(),
                TextColumn::make('stock_after')->label('变更后库存'),
                TextColumn::make('reason')->label('原因'),
                TextColumn::make('created_at')->label('时间')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
        ];
    }
}
