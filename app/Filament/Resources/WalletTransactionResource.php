<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages\ListWalletTransactions;
use App\Models\WalletTransaction;
use App\Support\AdminAccess;
use App\Support\Money;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationLabel = '钱包流水';

    protected static ?string $modelLabel = '钱包流水';

    protected static ?string $pluralModelLabel = '钱包流水';

    protected static string|\UnitEnum|null $navigationGroup = '财务';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    public static function canAccess(): bool
    {
        return AdminAccess::canAction('wallet.adjust') || AdminAccess::canAction('after_sales.refund');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'order', 'creator']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s')->sortable(),
            TextColumn::make('user.public_id')->label('用户 ID')->searchable(),
            TextColumn::make('user.name')->label('用户')->searchable(),
            TextColumn::make('order.order_number')->label('订单号')->searchable(),
            TextColumn::make('source')->label('来源')->badge()
                ->formatStateUsing(fn (string $state): string => WalletTransaction::sourceLabels()[$state] ?? $state),
            TextColumn::make('amount_cents')->label('金额')->formatStateUsing(fn ($state): string => Money::format((int) $state)),
            TextColumn::make('balance_after_cents')->label('余额')->formatStateUsing(fn ($state): string => Money::format((int) $state)),
            TextColumn::make('creator.name')->label('操作人'),
            TextColumn::make('after_sales_request_id')->label('售后记录')->toggleable(),
            TextColumn::make('note')->label('原因')->wrap()->limit(100)->tooltip(fn (WalletTransaction $record): ?string => $record->note),
        ])->filters([
            SelectFilter::make('source')->label('来源')->options(WalletTransaction::sourceLabels()),
        ])->defaultSort('id', 'desc')->recordUrl(null);
    }

    public static function getPages(): array
    {
        return ['index' => ListWalletTransactions::route('/')];
    }
}
