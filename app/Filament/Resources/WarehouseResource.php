<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\EditWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Models\Warehouse;
use App\Support\ChinaRegions;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Warehouse::class;
    protected static string $permissionArea = 'inventory';
    protected static ?string $navigationLabel = '仓库地址';
    protected static ?string $modelLabel = '仓库';
    protected static ?string $pluralModelLabel = '仓库地址';
    protected static string|\UnitEnum|null $navigationGroup = '仓库';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('仓库地址')->schema([
                TextInput::make('name')->label('仓库名称')->required()->maxLength(255),
                TextInput::make('contact_name')->label('联系人')->maxLength(100),
                TextInput::make('phone')->label('联系电话')->maxLength(60),
                TextInput::make('country')->label('国家')->default('中国')->maxLength(100),
                Select::make('province')->label('省份')->options(ChinaRegions::provinceOptions())->searchable(),
                TextInput::make('city')->label('城市')->maxLength(100),
                TextInput::make('district')->label('区 / 县')->maxLength(100),
                TextInput::make('street')->label('街道 / 门牌')->placeholder('可填写测试仓、A 仓等内部占位文字')->maxLength(255)->columnSpanFull(),
                Textarea::make('address')->label('完整地址补充')->placeholder('这里可以是实际地址，也可以是测试或内部识别用的位置说明。')->rows(3)->columnSpanFull(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
                Textarea::make('note')->label('备注')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('按省邮费')->description('维护该仓库发往各省的基础邮费；未单独设置的省份使用“其他地区”规则。')->schema([
                Repeater::make('shippingRates')->label('邮费规则')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')->label('规则名称')->required()->maxLength(255)->default('其他地区'),
                        Select::make('province_preset')
                            ->label('预设地区')
                            ->options(ChinaRegions::presetOptions())
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('provinces', ChinaRegions::provincesForPreset($state))),
                        Select::make('provinces')
                            ->label('省份')
                            ->options(ChinaRegions::provinceOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('可多选；默认规则不需要选择省份。'),
                        MoneyInput::cents(TextInput::make('fee_cents')->label('基础邮费（元）')->required()->minValue(0)),
                        Toggle::make('is_default')->label('其他地区')->default(false)->helperText('没有匹配省份时使用此规则。'),
                        Toggle::make('is_active')->label('启用')->default(true),
                        TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('仓库名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name', 'contact_name', 'phone', 'province', 'city', 'district', 'street', 'address', 'note'], $search))
                    ->sortable(),
                TextColumn::make('address')
                    ->label('地址')
                    ->formatStateUsing(fn (?string $state, Warehouse $record): string => $record->displayAddress() ?: '-')
                    ->limit(56),
                TextColumn::make('contact_name')->label('联系人')->toggleable(),
                TextColumn::make('phone')->label('电话')->toggleable(),
                TextColumn::make('stocks_count')
                    ->label('库存条目')
                    ->state(fn (Warehouse $record): int => $record->stocks()->count()),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
