<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\Money;
use App\Support\OrderStatusPresenter;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Order::class;
    protected static string $permissionArea = 'orders';
    protected static ?string $navigationLabel = '订单管理';
    protected static ?string $modelLabel = '订单';
    protected static ?string $pluralModelLabel = '订单管理';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('订单信息')->schema([
                TextInput::make('order_number')->label('订单号')->disabled(),
                TextInput::make('status')->label('订单状态')->disabled(),
                TextInput::make('payment_status')->label('付款状态')->disabled(),
                TextInput::make('subtotal_cents')->label('小计')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('discount_cents')->label('优惠')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('total_cents')->label('应付')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('coupon_code')->label('优惠码')->disabled(),
                TextInput::make('payment_proof_path')->label('付款凭证路径')->disabled()->columnSpanFull(),
                Placeholder::make('payment_proof_preview')
                    ->label('付款凭证图片')
                    ->content(fn (?Order $record): HtmlString => new HtmlString(
                        $record?->payment_proof_path
                            ? '<a href="'.e(route('admin.payment-proofs.show', $record)).'" target="_blank" rel="noopener"><img src="'.e(route('admin.payment-proofs.show', $record)).'" alt="付款凭证" style="max-width: 360px; max-height: 420px; border: 1px solid #cbd5e1; border-radius: 2px; object-fit: contain; background: #fff;" /></a>'
                            : '<span style="color:#64748b;">暂未上传付款凭证</span>'
                    ))
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('客户信息')->schema([
                TextInput::make('contact_name')->label('联系人')->disabled(),
                TextInput::make('contact_phone')->label('电话')->disabled(),
                TextInput::make('contact_email')->label('邮箱')->disabled(),
                Textarea::make('shipping_address')->label('收货地址')->disabled()->rows(3)->columnSpanFull(),
                Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500)->columnSpanFull(),
                Textarea::make('customer_note')->label('客户备注')->disabled()->rows(3)->columnSpanFull(),
                Textarea::make('admin_note')->label('后台备注')->rows(4)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('订单号')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['order_number'], $search))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('用户名')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $userQuery) => RegexSearch::where($userQuery, ['name'], $search))),
                TextColumn::make('user.email')
                    ->label('邮箱')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $userQuery) => RegexSearch::where($userQuery, ['email'], $search))),
                TextColumn::make('total_cents')->label('金额')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('status')
                    ->label('订单状态')
                    ->formatStateUsing(fn (?string $state): string => app(OrderStatusPresenter::class)->label($state))
                    ->color(fn (?string $state): string => app(OrderStatusPresenter::class)->color($state))
                    ->badge(),
                TextColumn::make('payment_status')->label('付款状态')->badge(),
                TextColumn::make('shippingCarrier.name')->label('物流')->toggleable(),
                TextColumn::make('tracking_number')->label('物流单号')->searchable()->toggleable(),
                TextColumn::make('created_at')->label('创建')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('confirmPayment')
                    ->label('确认收款')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->payment_status !== Order::PAYMENT_CONFIRMED && $record->status !== Order::STATUS_CANCELLED)
                    ->action(fn (Order $record) => app(OrderService::class)->confirmPayment($record, auth()->user())),
                Action::make('ship')
                    ->label('发货')
                    ->color('info')
                    ->form([
                        Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                        TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                        TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500),
                    ])
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PAID, Order::STATUS_PENDING_SHIPMENT, Order::STATUS_INCOMING], true))
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->ship($record, $data, auth()->user())),
                Action::make('incoming')
                    ->label('标记进货中')
                    ->color('info')
                    ->form([
                        Select::make('incoming_product_id')
                            ->label('进货中商品')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => Product::query()
                                ->where('status', Product::STATUS_INCOMING)
                                ->latest()
                                ->limit(80)
                                ->pluck('title', 'id')
                                ->all()),
                        Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                        TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                        TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500),
                    ])
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING_SHIPMENT && $record->items()->where('product_status', Product::STATUS_PRESALE)->exists())
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->markIncoming($record, $data, auth()->user())),
                Action::make('awaitingReceipt')
                    ->label('标记待签收')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SHIPPED)
                    ->action(fn (Order $record) => app(OrderService::class)->markAwaitingReceipt($record, auth()->user())),
                Action::make('rejectPayment')
                    ->label('驳回凭证')
                    ->color('warning')
                    ->form([
                        Textarea::make('admin_note')->label('驳回说明')->rows(3),
                    ])
                    ->visible(fn (Order $record): bool => $record->payment_status === Order::PAYMENT_SUBMITTED)
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->rejectPayment($record, $data['admin_note'] ?? null, auth()->user())),
                Action::make('fulfill')
                    ->label('标记完成')
                    ->form([
                        Textarea::make('admin_note')->label('特殊原因备注')->required()->rows(4)->helperText('后台直接完成会跳过用户确认签收，请填写用户可见的特殊原因。'),
                    ])
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PAID, Order::STATUS_AWAITING_RECEIPT], true))
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->fulfill($record, auth()->user(), $data['admin_note'] ?? null)),
                Action::make('cancel')
                    ->label('取消')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->isCancellable())
                    ->form([
                        Textarea::make('admin_note')->label('取消说明')->rows(3),
                    ])
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->cancel($record, auth()->user(), $data['admin_note'] ?? null)),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
