<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\WarehouseMovementResource\Pages\ListWarehouseMovements;
use App\Models\WarehouseMovement;
use App\Support\RegexSearch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseMovementResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = WarehouseMovement::class;
    protected static string $permissionArea = 'inventory';
    protected static ?string $navigationLabel = '仓库流水';
    protected static ?string $modelLabel = '仓库流水';
    protected static ?string $pluralModelLabel = '仓库流水';
    protected static string|\UnitEnum|null $navigationGroup = '仓库';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?int $navigationSort = 30;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['stock', 'procurement', 'order', 'user', 'variant']))
            ->columns([
                TextColumn::make('stock.name')
                    ->label('仓库条目')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('stock', fn (Builder $stockQuery) => RegexSearch::where($stockQuery, ['name', 'sku'], $search)))
                    ->limit(32),
                TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (?string $state): string => WarehouseMovement::typeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('delta')->label('变化')->sortable(),
                TextColumn::make('quantity_after')->label('变更后数量')->sortable(),
                TextColumn::make('variant.sku')->label('SKU')->toggleable(),
                TextColumn::make('order.order_number')->label('订单')->toggleable(),
                TextColumn::make('procurement.name')->label('采购')->limit(28)->toggleable(),
                TextColumn::make('user.name')->label('操作人')->toggleable(),
                TextColumn::make('note')->label('备注')->limit(36)->toggleable(),
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseMovements::route('/'),
        ];
    }
}
