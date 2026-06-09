<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\OrderStatusSettingResource\Pages\ManageOrderStatusSettings;
use App\Models\OrderStatusSetting;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

class OrderStatusSettingResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = OrderStatusSetting::class;
    protected static string $permissionArea = 'orders';
    protected static ?string $navigationLabel = '订单状态设置';
    protected static ?string $modelLabel = '订单状态';
    protected static ?string $pluralModelLabel = '订单状态';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('名称')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::snake((string) $state))),
            TextInput::make('code')
                ->label('编码')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state): string => Str::snake(trim((string) $state))),
            Select::make('color')
                ->label('颜色')
                ->required()
                ->options([
                    'gray' => '灰色',
                    'primary' => '蓝色',
                    'success' => '绿色',
                    'warning' => '黄色',
                    'danger' => '红色',
                    'info' => '信息蓝',
                ])
                ->default('gray'),
            Select::make('icon')
                ->label('图标')
                ->searchable()
                ->options([
                    'heroicon-o-banknotes' => '付款',
                    'heroicon-o-clock' => '等待',
                    'heroicon-o-check-circle' => '完成/确认',
                    'heroicon-o-archive-box' => '归档/交付',
                    'heroicon-o-truck' => '运输',
                    'heroicon-o-x-circle' => '取消',
                    'heroicon-o-exclamation-circle' => '提醒',
                ]),
            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->label('启用')
                ->default(true),
            Textarea::make('description')
                ->label('说明')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('icon')->label('图标')->icon(fn (?string $state): ?string => $state)->color(fn (OrderStatusSetting $record): string => $record->color),
                TextColumn::make('label')->label('名称')->searchable()->sortable()->badge()->color(fn (OrderStatusSetting $record): string => $record->color),
                TextColumn::make('code')->label('编码')->searchable()->toggleable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('description')->label('说明')->limit(32)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('更新')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrderStatusSettings::route('/'),
        ];
    }
}
