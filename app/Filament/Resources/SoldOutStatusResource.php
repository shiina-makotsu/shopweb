<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SoldOutStatusResource\Pages\ManageSoldOutStatuses;
use App\Models\SoldOutStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class SoldOutStatusResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SoldOutStatus::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '售罄状态';
    protected static ?string $modelLabel = '售罄状态';
    protected static ?string $pluralModelLabel = '售罄状态';
    protected static string|\UnitEnum|null $navigationGroup = '目录';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;
    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::snake($state))),
            TextInput::make('code')->label('编码')->required()->unique(ignoreRecord: true)->maxLength(100)
                ->dehydrateStateUsing(fn (?string $state) => Str::snake(trim((string) $state))),
            Select::make('behavior')->label('行为')->required()->options([
                SoldOutStatus::BEHAVIOR_HIDE => '隐藏不可售',
                SoldOutStatus::BEHAVIOR_SHOW => '展示售罄',
                SoldOutStatus::BEHAVIOR_CONTACT => '允许咨询',
            ])->default(SoldOutStatus::BEHAVIOR_HIDE),
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
                TextColumn::make('behavior')->label('行为')->badge(),
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
            'index' => ManageSoldOutStatuses::route('/'),
        ];
    }
}
