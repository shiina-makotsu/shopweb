<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\FlashSaleResource\Pages\CreateFlashSale;
use App\Filament\Resources\FlashSaleResource\Pages\EditFlashSale;
use App\Filament\Resources\FlashSaleResource\Pages\ListFlashSales;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\MoneyInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlashSaleResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = FlashSale::class;
    protected static string $permissionArea = 'coupons';
    protected static ?string $navigationLabel = '秒杀';
    protected static ?string $modelLabel = '秒杀';
    protected static ?string $pluralModelLabel = '秒杀';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;
    protected static ?int $navigationSort = 23;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('秒杀活动')->schema([
                TextInput::make('name')->label('活动名称')->required()->maxLength(255),
                Select::make('product_id')
                    ->label('商品')
                    ->options(fn (): array => Product::query()
                        ->whereIn('status', [Product::STATUS_PUBLISHED, Product::STATUS_PRESALE])
                        ->orderBy('title')
                        ->limit(200)
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('product_variant_ids')
                    ->label('允许选择的规格')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('留空表示该商品所有启用规格都可在秒杀结算页选择。')
                    ->options(fn ($get): array => ProductVariant::query()
                        ->where('product_id', $get('product_id'))
                        ->where('is_active', true)
                        ->get()
                        ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->sku.' / '.$variant->specLabel().' / 库存 '.$variant->stock])
                        ->all()),
                ...MoneyInput::convertedCents(TextInput::make('sale_price_cents')->label('秒杀价')->required()->minValue(1)),
                TextInput::make('quantity_limit')
                    ->label('秒杀名额')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('保存时系统会按当前可用库存限制实际可抢数量，前台也会实时取名额和库存的较小值。'),
                TextInput::make('sold_quantity')->label('已抢名额')->numeric()->default(0)->disabled()->dehydrated(false),
                DateTimePicker::make('starts_at')->label('开始时间')->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label('结束时间')->seconds(false),
                Toggle::make('is_active')->label('启用')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('活动')->searchable(),
                TextColumn::make('product.title')->label('商品')->searchable(),
                TextColumn::make('sale_price_cents')->label('秒杀价')->formatStateUsing(fn ($state): string => \App\Support\Money::format((int) $state)),
                TextColumn::make('quantity_limit')->label('名额'),
                TextColumn::make('sold_quantity')->label('已抢'),
                TextColumn::make('starts_at')->label('开始')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('ends_at')->label('结束')->dateTime('Y-m-d H:i')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSales::route('/'),
            'create' => CreateFlashSale::route('/create'),
            'edit' => EditFlashSale::route('/{record}/edit'),
        ];
    }
}
