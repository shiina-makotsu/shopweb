<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockVariants extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?string $heading = '低库存提醒';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('商品')
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->label('售价')
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextColumn::make('stock')
                    ->label('库存')
                    ->sortable(),
                TextColumn::make('low_stock_threshold')
                    ->label('低库存阈值')
                    ->sortable(),
            ])
            ->defaultSort('stock')
            ->recordUrl(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->product]));
    }

    protected function getTableQuery(): Builder
    {
        return ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn (Builder $query): Builder => $query->whereNotIn('fulfillment_type', [
                Product::FULFILLMENT_ONLINE,
                Product::FULFILLMENT_CONTACT_LEGACY,
            ]))
            ->whereColumn('stock', '<=', 'low_stock_threshold');
    }
}
