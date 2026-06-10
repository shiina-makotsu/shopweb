<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CostEntryResource\Pages\CreateCostEntry;
use App\Filament\Resources\CostEntryResource\Pages\EditCostEntry;
use App\Filament\Resources\CostEntryResource\Pages\ListCostEntries;
use App\Models\CostEntry;
use App\Models\Procurement;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CostEntryResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = CostEntry::class;
    protected static string $permissionArea = 'finance';
    protected static ?string $navigationLabel = '成本条目';
    protected static ?string $modelLabel = '成本条目';
    protected static ?string $pluralModelLabel = '成本条目';
    protected static string|\UnitEnum|null $navigationGroup = '财务';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return AdminAccess::canAction('finance.manage_costs');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::canAction('finance.manage_costs');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::canAction('finance.manage_costs') && ! $record->is_auto;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('成本信息')->schema([
                Select::make('category')->label('成本分类')->options(CostEntry::categoryOptions())->default(CostEntry::CATEGORY_OTHER)->required(),
                TextInput::make('name')->label('名称')->required()->maxLength(255),
                TextInput::make('amount_cents')->label('金额（分）')->numeric()->minValue(0)->default(0)->required(),
                Select::make('procurement_id')
                    ->label('关联采购')
                    ->options(fn (): array => Procurement::query()->latest()->limit(100)->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                TextInput::make('country')->label('国家/地区')->maxLength(20),
                TextInput::make('tax_rate')->label('税率')->numeric()->step('0.0001'),
                Toggle::make('is_auto')->label('自动生成')->disabled()->dehydrated(false),
                Textarea::make('note')->label('备注')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name', 'note'], $search))
                    ->sortable(),
                TextColumn::make('category')->label('分类')->formatStateUsing(fn (?string $state): string => CostEntry::categoryOptions()[$state] ?? ($state ?: '-'))->badge()->sortable(),
                TextColumn::make('amount_cents')->label('金额')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('procurement.name')->label('采购')->limit(32)->toggleable(),
                TextColumn::make('country')->label('国家/地区')->toggleable(),
                TextColumn::make('is_auto')->label('来源')->formatStateUsing(fn (bool $state): string => $state ? '自动' : '手工')->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (CostEntry $record): bool => static::canDelete($record))])
            ->toolbarActions([DeleteBulkAction::make()->visible(fn (): bool => AdminAccess::canAction('finance.manage_costs'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostEntries::route('/'),
            'create' => CreateCostEntry::route('/create'),
            'edit' => EditCostEntry::route('/{record}/edit'),
        ];
    }
}
