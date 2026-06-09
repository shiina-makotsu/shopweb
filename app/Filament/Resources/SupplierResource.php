<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupplierResource\Pages\ManageSuppliers;
use App\Models\Supplier;
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

class SupplierResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Supplier::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '供应商';
    protected static ?string $modelLabel = '供应商';
    protected static ?string $pluralModelLabel = '供应商';
    protected static string|\UnitEnum|null $navigationGroup = '目录';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('code', Str::upper(Str::slug($state, '_')))),
            TextInput::make('code')->label('编码')->required()->unique(ignoreRecord: true)->maxLength(100)
                ->dehydrateStateUsing(fn (?string $state) => Str::upper(trim((string) $state))),
            TextInput::make('contact_name')->label('联系人')->maxLength(255),
            TextInput::make('contact_phone')->label('电话')->maxLength(255),
            TextInput::make('contact_email')->label('邮箱')->email()->maxLength(255),
            Toggle::make('is_active')->label('启用')->default(true),
            Textarea::make('address')->label('地址')->rows(2)->columnSpanFull(),
            Textarea::make('note')->label('备注')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('code')->label('编码')->searchable(),
                TextColumn::make('contact_name')->label('联系人')->searchable(),
                TextColumn::make('contact_phone')->label('电话'),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }
}
