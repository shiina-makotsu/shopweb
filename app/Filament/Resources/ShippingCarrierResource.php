<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ShippingCarrierResource\Pages\ManageShippingCarriers;
use App\Models\ShippingCarrier;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ShippingCarrierResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ShippingCarrier::class;
    protected static string $permissionArea = 'orders';
    protected static ?string $navigationLabel = '物流设置';
    protected static ?string $modelLabel = '物流承运商';
    protected static ?string $pluralModelLabel = '物流设置';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?int $navigationSort = 38;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名称')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::slug((string) $state, '_'))),
            TextInput::make('code')
                ->label('编码')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state): string => Str::slug(trim((string) $state), '_')),
            TextInput::make('tracking_url_template')
                ->label('查询链接模板')
                ->helperText('可使用 {tracking_number} 作为物流单号占位符。')
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('api_endpoint')
                ->label('API 地址')
                ->maxLength(500)
                ->columnSpanFull(),
            Textarea::make('api_notes')
                ->label('API / 对接说明')
                ->rows(4)
                ->columnSpanFull(),
            Toggle::make('is_international')
                ->label('国际物流')
                ->default(false),
            Toggle::make('is_active')
                ->label('启用')
                ->default(true),
            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('code')->label('编码')->searchable()->toggleable(),
                IconColumn::make('is_international')->label('国际')->boolean(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('tracking_url_template')->label('查询模板')->limit(48)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('api_endpoint')->label('API')->limit(48)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('更新')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShippingCarriers::route('/'),
        ];
    }
}
