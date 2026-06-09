<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\DeliveryStatusResource\Pages\ManageDeliveryStatuses;
use App\Models\DeliveryStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DeliveryStatusResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = DeliveryStatus::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '交付状态';
    protected static ?string $modelLabel = '交付状态';
    protected static ?string $pluralModelLabel = '交付状态';
    protected static string|\UnitEnum|null $navigationGroup = '目录';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::snake($state))),
            TextInput::make('code')->label('编码')->required()->unique(ignoreRecord: true)->maxLength(100)
                ->dehydrateStateUsing(fn (?string $state) => Str::snake(trim((string) $state))),
            TextInput::make('description')->label('说明')->maxLength(255)->columnSpanFull(),
            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Toggle::make('is_active')->label('启用')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('code')->label('编码')->searchable(),
                TextColumn::make('description')->label('说明')->limit(40),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeliveryStatuses::route('/'),
        ];
    }
}
