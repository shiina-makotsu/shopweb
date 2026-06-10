<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\PaymentVerificationLogResource\Pages\ListPaymentVerificationLogs;
use App\Models\PaymentVerificationLog;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentVerificationLogResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = PaymentVerificationLog::class;
    protected static string $permissionArea = 'payments';
    protected static ?string $navigationLabel = '付款校验日志';
    protected static ?string $modelLabel = '付款校验日志';
    protected static ?string $pluralModelLabel = '付款校验日志';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('订单号')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('order', fn (Builder $orderQuery) => RegexSearch::where($orderQuery, ['order_number'], $search))),
                TextColumn::make('user.name')->label('用户')->toggleable(),
                TextColumn::make('payment_proof_path')
                    ->label('截图文件')
                    ->limit(32)
                    ->searchable(),
                TextColumn::make('expected_order_number')->label('应有订单号')->toggleable(),
                TextColumn::make('detected_order_number')->label('识别订单号')->toggleable(),
                TextColumn::make('expected_amount_cents')->label('应付金额')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('detected_amount_cents')->label('识别金额')->formatStateUsing(fn ($state): string => $state === null ? '-' : Money::format((int) $state))->toggleable(),
                TextColumn::make('auto_result')->label('自动校验')->badge()->sortable(),
                TextColumn::make('manual_result')->label('人工复核')->badge()->toggleable(),
                TextColumn::make('actor.name')->label('复核人')->toggleable(),
                TextColumn::make('created_at')->label('创建')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentVerificationLogs::route('/'),
        ];
    }
}
