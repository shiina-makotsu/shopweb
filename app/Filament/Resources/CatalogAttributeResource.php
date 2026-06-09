<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CatalogAttributeResource\Pages\ManageCatalogAttributes;
use App\Models\CatalogAttribute;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CatalogAttributeResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = CatalogAttribute::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '属性';
    protected static ?string $modelLabel = '属性';
    protected static ?string $pluralModelLabel = '属性';
    protected static string|\UnitEnum|null $navigationGroup = '目录';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::snake($state))),
            TextInput::make('code')->label('编码')->required()->unique(ignoreRecord: true)->maxLength(100)
                ->dehydrateStateUsing(fn (?string $state) => Str::snake(trim((string) $state))),
            Select::make('type')->label('类型')->required()->options([
                CatalogAttribute::TYPE_TEXT => '文本',
                CatalogAttribute::TYPE_NUMBER => '数字',
                CatalogAttribute::TYPE_SELECT => '选项',
            ])->default(CatalogAttribute::TYPE_TEXT),
            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Toggle::make('is_active')->label('启用')->default(true),
            KeyValue::make('options')
                ->label('选项')
                ->keyLabel('值')
                ->valueLabel('显示名称')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('code')->label('编码')->searchable(),
                TextColumn::make('type')->label('类型')->badge(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCatalogAttributes::route('/'),
        ];
    }
}
