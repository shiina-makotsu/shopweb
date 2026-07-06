<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingPaymentOrders extends TableWidget
{
    protected static bool $isLazy = true;

    protected static ?string $heading = '待确认付款';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('order_number')
                    ->label('订单号')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('用户')
                    ->searchable(),
                TextColumn::make('contact_name')
                    ->label('联系人')
                    ->searchable(),
                TextColumn::make('total_cents')
                    ->label('金额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state))
                    ->sortable(),
                TextColumn::make('payment_submitted_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('payment_submitted_at', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record]));
    }

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->with('user')
            ->awaitingPaymentReview();
    }
}
