<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CostEntryResource\Pages\CreateCostEntry;
use App\Filament\Resources\CostEntryResource\Pages\EditCostEntry;
use App\Filament\Resources\CostEntryResource\Pages\ListCostEntries;
use App\Models\CostEntry;
use App\Models\Procurement;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\Money;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use BackedEnum;
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
use UnitEnum;

class CostEntryResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = CostEntry::class;

    protected static string $permissionArea = 'finance';

    protected static ?string $navigationLabel = '成本条目';

    protected static ?string $modelLabel = '成本条目';

    protected static ?string $pluralModelLabel = '成本条目';

    protected static string|UnitEnum|null $navigationGroup = '财务';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

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
            Section::make('成本信息')
                ->schema([
                    Select::make('category')
                        ->label('成本分类')
                        ->options(CostEntry::categoryOptions())
                        ->default(CostEntry::CATEGORY_OTHER)
                        ->live()
                        ->required(),
                    Select::make('application_type')
                        ->label('生效类型')
                        ->options(CostEntry::applicationTypeOptions())
                        ->default(CostEntry::APPLICATION_RECURRING)
                        ->required()
                        ->helperText('持续成本会直接进入利润计算；采购触发成本仅在关联采购并标记生效后计算。'),
                    TextInput::make('name')
                        ->label('名称')
                        ->required()
                        ->maxLength(255),
                    Section::make('金额')
                        ->schema([
                            ...MoneyInput::conversionControls('currency', 'currency_code', 'currency_unit', 'exchange_rate', dehydrated: true),
                            TextInput::make('original_amount')
                                ->label('具体金额')
                                ->numeric()
                                ->step('0.0001')
                                ->minValue(0)
                                ->default(0)
                                ->required()
                                ->helperText('保存时按财务货币页的自动汇率快照折算为基准货币。'),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    TextInput::make('amount_cents')
                        ->label('折算金额（基准货币分）')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('保存后自动生成，用于利润统计和公式变量。'),
                    Select::make('procurement_id')
                        ->label('关联采购')
                        ->options(fn (): array => Procurement::query()->latest()->limit(100)->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Toggle::make('is_effective')
                        ->label('已生效')
                        ->default(true)
                        ->helperText('未生效成本不会进入利润统计；采购保存自动生成的成本会自动标记为已生效。'),
                    TextInput::make('effective_times')
                        ->label('生效次数')
                        ->numeric()
                        ->minValue(0)
                        ->default(1),
                    TextInput::make('effective_quantity')
                        ->label('生效数量')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('采购触发成本会记录采购数量；持续成本可留空或填 0。'),
                    TextInput::make('country')
                        ->label('国家/地区')
                        ->maxLength(20),
                    TextInput::make('tax_rate')
                        ->label('税率')
                        ->numeric()
                        ->step('0.0001'),
                    Toggle::make('is_auto')
                        ->label('自动生成')
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('note')
                        ->label('备注')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
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
                TextColumn::make('category')
                    ->label('分类')
                    ->formatStateUsing(fn (?string $state): string => CostEntry::categoryOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount_cents')
                    ->label('基准金额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))
                    ->sortable(),
                TextColumn::make('original_amount')
                    ->label('原始金额')
                    ->formatStateUsing(fn ($state, CostEntry $record): string => CurrencyUnit::formatOriginal($state, $record->currency_code, $record->currency_unit))
                    ->toggleable(),
                TextColumn::make('procurement.name')
                    ->label('采购')
                    ->limit(32)
                    ->toggleable(),
                TextColumn::make('application_type')
                    ->label('生效类型')
                    ->formatStateUsing(fn (?string $state): string => CostEntry::applicationTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('is_effective')
                    ->label('是否生效')
                    ->formatStateUsing(fn (bool $state): string => $state ? '已生效' : '未生效')
                    ->badge(),
                TextColumn::make('effective_times')
                    ->label('生效次数')
                    ->toggleable(),
                TextColumn::make('effective_quantity')
                    ->label('生效数量')
                    ->toggleable(),
                TextColumn::make('country')
                    ->label('国家/地区')
                    ->toggleable(),
                TextColumn::make('is_auto')
                    ->label('来源')
                    ->formatStateUsing(fn (bool $state): string => $state ? '自动' : '手工')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('更新')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CostEntry $record): bool => static::canDelete($record)),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->visible(fn (): bool => AdminAccess::canAction('finance.manage_costs')),
            ]);
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
