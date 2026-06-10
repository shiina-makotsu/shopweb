<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\OrderStatusPresenter;
use App\Support\OrderTimeline;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
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
                TextInput::make('shipping_fee_cents')->label('邮费')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('total_cents')->label('应付')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('coupon_code')->label('优惠码')->disabled(),
                TextInput::make('payment_proof_path')->label('付款凭证路径')->disabled()->columnSpanFull(),
                Placeholder::make('user_deleted_flag')
                    ->label('用户删除状态')
                    ->content(fn (?Order $record): string => $record?->user_deleted_at
                        ? '用户已删除：'.$record->user_deleted_at->format('Y-m-d H:i')
                        : '用户可见'),
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
                TextInput::make('shipping_province')->label('收货省份')->disabled(),
                Textarea::make('shipping_address')->label('收货地址')->disabled()->rows(3)->columnSpanFull(),
                Textarea::make('shipment_notice')->label('多仓发货提醒')->disabled()->rows(3)->columnSpanFull(),
                Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500)->columnSpanFull(),
                Textarea::make('digital_delivery_content')->label('线上交付内容')->rows(4)->columnSpanFull(),
                TextInput::make('digital_delivery_code')->label('兑换码/序列号')->maxLength(255),
                Placeholder::make('digital_delivery_files')
                    ->label('线上交付附件')
                    ->content(fn (?Order $record): string => implode("\n", $record?->digital_delivery_attachment_paths ?: []) ?: '暂无附件')
                    ->columnSpanFull(),
                Textarea::make('customer_note')->label('客户备注')->disabled()->rows(3)->columnSpanFull(),
                Textarea::make('admin_note')->label('后台备注')->rows(4)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('业务时间线')->schema([
                Placeholder::make('timeline')
                    ->label('')
                    ->content(fn (?Order $record): HtmlString => static::timelineHtml($record))
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    private static function timelineHtml(?Order $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<p style="color:#64748b;">保存订单后显示业务时间线。</p>');
        }

        $events = app(OrderTimeline::class)->events($record);

        if ($events === []) {
            return new HtmlString('<p style="color:#64748b;">暂无业务时间线。</p>');
        }

        $items = collect($events)->map(function (array $event): string {
            $time = e($event['time']->format('Y-m-d H:i'));
            $label = e($event['label']);
            $detail = e($event['detail'] ?: '-');
            $actor = $event['actor'] ? '<span style="color:#64748b;">'.e($event['actor']).'</span>' : '';

            return <<<HTML
                <li style="display:grid;grid-template-columns:140px 1fr;gap:12px;padding:10px 0;border-bottom:1px solid #e2e8f0;">
                    <time style="color:#64748b;font-size:12px;">{$time}</time>
                    <div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <strong>{$label}</strong>
                            {$actor}
                        </div>
                        <p style="margin-top:4px;color:#475569;font-size:13px;line-height:1.5;">{$detail}</p>
                    </div>
                </li>
            HTML;
        })->implode('');

        return new HtmlString('<ol style="margin:0;padding:0;list-style:none;">'.$items.'</ol>');
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
                TextColumn::make('shipping_fee_cents')->label('邮费')->formatStateUsing(fn ($state) => Money::format((int) $state))->sortable()->toggleable(),
                TextColumn::make('status')
                    ->label('订单状态')
                    ->formatStateUsing(fn (?string $state): string => app(OrderStatusPresenter::class)->label($state))
                    ->color(fn (?string $state): string => app(OrderStatusPresenter::class)->color($state))
                    ->badge(),
                TextColumn::make('payment_status')->label('付款状态')->badge(),
                TextColumn::make('user_deleted_at')
                    ->label('用户可见')
                    ->formatStateUsing(fn ($state): string => $state ? '用户已删除' : '用户可见')
                    ->badge()
                    ->toggleable(),
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
                    ->visible(fn (Order $record): bool => AdminAccess::canAction('orders.confirm_payment') && $record->payment_status !== Order::PAYMENT_CONFIRMED && $record->status !== Order::STATUS_CANCELLED)
                    ->action(fn (Order $record) => app(OrderService::class)->confirmPayment($record, auth()->user())),
                Action::make('ship')
                    ->label('发货')
                    ->color('info')
                    ->form([
                        Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                        TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                        TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500),
                        Textarea::make('digital_delivery_content')
                            ->label('线上交付内容')
                            ->rows(4)
                            ->helperText('线上交付商品可填写图片说明、兑换码使用说明等。'),
                        TextInput::make('digital_delivery_code')->label('兑换码/序列号')->maxLength(255),
                        FileUpload::make('digital_delivery_attachments')
                            ->label('线上交付附件')
                            ->disk('digital_deliveries')
                            ->directory(fn (Order $record): string => $record->order_number)
                            ->multiple()
                            ->maxSize(20480)
                            ->preserveFilenames()
                            ->helperText('可上传图片或文件。用户下载附件后订单会自动完成。'),
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
                Action::make('returnToWarehouse')
                    ->label('退回入库')
                    ->color('warning')
                    ->form([
                        Textarea::make('admin_note')
                            ->label('退回/拒收说明')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_SHIPPED, Order::STATUS_AWAITING_RECEIPT], true))
                    ->action(fn (Order $record, array $data) => app(OrderService::class)->returnToWarehouse($record, auth()->user(), $data['admin_note'] ?? null)),
                Action::make('rejectPayment')
                    ->label('驳回凭证')
                    ->color('warning')
                    ->form([
                        Textarea::make('admin_note')->label('驳回说明')->rows(3),
                    ])
                    ->visible(fn (Order $record): bool => AdminAccess::canAction('orders.reject_payment') && $record->payment_status === Order::PAYMENT_SUBMITTED)
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
