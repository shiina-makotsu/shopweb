<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\WarehouseStockResource\Pages\CreateWarehouseStock;
use App\Filament\Resources\WarehouseStockResource\Pages\EditWarehouseStock;
use App\Filament\Resources\WarehouseStockResource\Pages\ListWarehouseStocks;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Procurement;
use App\Models\WarehouseMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseService;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStockResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = WarehouseStock::class;
    protected static string $permissionArea = 'inventory';
    protected static ?string $navigationLabel = '仓管';
    protected static ?string $modelLabel = '仓管库存';
    protected static ?string $pluralModelLabel = '仓管';
    protected static string|\UnitEnum|null $navigationGroup = '仓库';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('仓库条目')->schema([
                Select::make('product_id')
                    ->label('商品')
                    ->options(fn (): array => Product::query()->orderBy('title')->limit(200)->pluck('title', 'id')->all())
                    ->searchable()
                    ->preload(),
                Select::make('product_variant_id')
                    ->label('SKU')
                    ->options(fn (): array => ProductVariant::query()->with('product')->latest()->limit(200)->get()
                        ->mapWithKeys(fn (ProductVariant $variant): array => [
                            $variant->id => trim(($variant->product?->title ?: '未关联商品').' / '.$variant->sku),
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                Select::make('procurement_id')
                    ->label('关联采购')
                    ->options(fn (): array => Procurement::query()->latest()->limit(100)->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('name')->label('仓库名称')->required()->maxLength(255),
                TextInput::make('sku')->label('仓库 SKU')->maxLength(255),
                TextInput::make('quantity')->label('仓内数量')->numeric()->default(0)->required(),
                TextInput::make('reserved_quantity')->label('预留数量')->numeric()->minValue(0)->default(0),
                Textarea::make('note')->label('备注')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('仓库名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name', 'sku', 'note'], $search))
                    ->sortable(),
                TextColumn::make('product.title')->label('商品')->limit(32)->toggleable(),
                TextColumn::make('variant.sku')->label('SKU')->searchable()->toggleable(),
                TextColumn::make('procurement.name')->label('采购')->limit(32)->toggleable(),
                TextColumn::make('quantity')->label('仓内数量')->sortable(),
                TextColumn::make('reserved_quantity')->label('预留')->sortable(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('adjust')
                    ->label('调整')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->form([
                        Select::make('type')
                            ->label('调整类型')
                            ->options([
                                WarehouseMovement::TYPE_MANUAL_IN => '人工入库',
                                WarehouseMovement::TYPE_MANUAL_OUT => '人工出库',
                                WarehouseMovement::TYPE_PROCESSING_IN => '加工入库',
                                WarehouseMovement::TYPE_PROCESSING_OUT => '加工出库',
                                WarehouseMovement::TYPE_ADJUSTMENT => '数量校准',
                            ])
                            ->default(WarehouseMovement::TYPE_ADJUSTMENT)
                            ->required(),
                        TextInput::make('delta')
                            ->label('变化数量')
                            ->helperText('增加写正数，减少写负数；二次加工拆分可用加工入库/加工出库成对记录。')
                            ->numeric()
                            ->required(),
                        Textarea::make('note')->label('原因备注')->rows(3),
                    ])
                    ->action(function (WarehouseStock $record, array $data): void {
                        app(WarehouseService::class)->adjust(
                            $record,
                            (int) $data['delta'],
                            (string) $data['type'],
                            auth()->user(),
                            $data['note'] ?? null,
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseStocks::route('/'),
            'create' => CreateWarehouseStock::route('/create'),
            'edit' => EditWarehouseStock::route('/{record}/edit'),
        ];
    }
}
