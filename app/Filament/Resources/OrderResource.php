<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\OrderStatusPresenter;
use App\Support\OrderTimeline;
use App\Support\RegexSearch;
use App\Support\Url;
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
                Select::make('status')
                    ->label('订单状态')
                    ->options(fn (): array => app(OrderStatusPresenter::class)->options())
                    ->required(),
                Select::make('payment_status')
                    ->label('付款状态')
                    ->options([
                        Order::PAYMENT_PENDING => '待付款',
                        Order::PAYMENT_SUBMITTED => '已提交凭证',
                        Order::PAYMENT_CONFIRMED => '已确认付款',
                        Order::PAYMENT_REJECTED => '已驳回',
                    ])
                    ->required(),
                TextInput::make('subtotal_cents')->label('小计')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('discount_cents')->label('优惠')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('shipping_fee_cents')->label('邮费')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('total_cents')->label('应付')->disabled()->formatStateUsing(fn ($state): string => Money::format((int) $state)),
                TextInput::make('coupon_code')->label('优惠码')->disabled(),
                TextInput::make('payment_proof_path')->label('付款凭证路径')->disabled()->columnSpanFull(),
                Textarea::make('payment_text_proof')->label('口令红包 / 文字付款凭证')->disabled()->rows(3)->columnSpanFull(),
                Placeholder::make('user_deleted_flag')
                    ->label('用户删除状态')
                    ->content(fn (?Order $record): string => $record?->user_deleted_at
                        ? '用户已删除：'.$record->user_deleted_at->format('Y-m-d H:i')
                        : '用户可见'),
                Placeholder::make('payment_proof_preview')
                    ->label('付款凭证图片')
                    ->content(fn (?Order $record): HtmlString => new HtmlString(
                        $record?->payment_proof_path
                            ? '<a href="'.e(Url::route('admin.payment-proofs.show', $record)).'" target="_blank" rel="noopener"><img src="'.e(Url::route('admin.payment-proofs.show', $record)).'" alt="付款凭证" style="max-width: 360px; max-height: 420px; border: 1px solid #cbd5e1; border-radius: 2px; object-fit: contain; background: #fff;" /></a>'
                            : '<span style="color:#64748b;">暂未上传付款凭证</span>'
                    ))
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('订单商品')->schema([
                Placeholder::make('order_items')
                    ->label('')
                    ->content(fn (?Order $record): HtmlString => static::orderItemsHtml($record))
                    ->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('客户信息')->schema([
                TextInput::make('contact_name')->label('联系人')->required()->maxLength(255),
                TextInput::make('contact_phone')->label('电话')->required()->maxLength(255),
                TextInput::make('contact_email')->label('邮箱')->email()->maxLength(255),
                TextInput::make('shipping_province')->label('收货省份')->maxLength(255),
                TextInput::make('shipping_city')->label('收货城市')->maxLength(255),
                TextInput::make('shipping_district')->label('收货区县')->maxLength(255),
                TextInput::make('shipping_street')->label('收货街道')->maxLength(255),
                TextInput::make('shipping_detail')->label('详细地址')->maxLength(255)->columnSpanFull(),
                Textarea::make('shipping_address')->label('收货地址汇总')->rows(3)->columnSpanFull(),
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
            Section::make('修改记录')->schema([
                Textarea::make('manual_update_note')
                    ->label('本次修改备注')
                    ->dehydrated(false)
                    ->rows(3)
                    ->helperText('只有订单字段发生变化时才需要填写；保存后会写入后台操作日志和订单时间线。')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
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

    private static function orderItemsHtml(?Order $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<p style="color:#64748b;">保存订单后显示商品明细。</p>');
        }

        $record->loadMissing(['items.incomingProduct', 'items.productVariant']);

        if ($record->items->isEmpty()) {
            return new HtmlString('<p style="color:#64748b;">该订单暂无商品明细。</p>');
        }

        $rows = $record->items->values()->map(function ($item, int $index): string {
            $number = $index + 1;
            $name = e($item->product_title);
            $sku = e($item->variant_sku ?: '-');
            $specsLabel = $item->productVariant?->displayName()
                ?: ProductVariant::specsLabel(is_array($item->variant_specs) ? $item->variant_specs : []);
            $specs = e($specsLabel);
            $productStatus = e(static::productStatusLabel($item->product_status));
            $itemStatus = e($item->status ?: '-');
            $unitPrice = e(Money::format((int) $item->unit_price_cents));
            $quantity = (int) $item->quantity;
            $lineTotal = e(Money::format((int) $item->line_total_cents));
            $discount = (int) ($item->discount_cents ?? 0);
            $discountLabel = $discount > 0 ? e(Money::format($discount)) : '-';
            $coupon = filled($item->coupon_code) ? '<span style="color:#64748b;">'.e($item->coupon_code).'</span>' : '';
            $incoming = $item->incomingProduct
                ? '<div style="margin-top:4px;color:#0369a1;">进货商品：'.e($item->incomingProduct->title).'</div>'
                : '';

            return <<<HTML
                <tr>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;color:#64748b;">{$number}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;min-width:220px;">
                        <div style="font-weight:600;color:#0f172a;">{$name}</div>
                        <div style="margin-top:4px;color:#64748b;font-size:12px;">SKU：{$sku}</div>
                        {$incoming}
                    </td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;min-width:180px;color:#334155;">{$specs}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;color:#334155;">{$productStatus}<br><span style="color:#64748b;font-size:12px;">{$itemStatus}</span></td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;text-align:right;white-space:nowrap;">{$unitPrice}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;text-align:center;">{$quantity}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;text-align:right;white-space:nowrap;">{$lineTotal}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;text-align:right;white-space:nowrap;">{$discountLabel}<br>{$coupon}</td>
                </tr>
            HTML;
        })->implode('');

        return new HtmlString(<<<HTML
            <div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc;color:#475569;text-align:left;">
                            <th style="padding:10px 12px;width:44px;">#</th>
                            <th style="padding:10px 12px;">商品</th>
                            <th style="padding:10px 12px;">规格</th>
                            <th style="padding:10px 12px;">状态</th>
                            <th style="padding:10px 12px;text-align:right;">单价</th>
                            <th style="padding:10px 12px;text-align:center;">数量</th>
                            <th style="padding:10px 12px;text-align:right;">小计</th>
                            <th style="padding:10px 12px;text-align:right;">优惠</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        HTML);
    }

    private static function orderItemsSummary(Order $record): string
    {
        $record->loadMissing('items.productVariant');

        return $record->items
            ->map(fn ($item): string => trim($item->product_title.' / '.($item->productVariant?->displayName() ?: ProductVariant::specsLabel(is_array($item->variant_specs) ? $item->variant_specs : [])).' x'.$item->quantity))
            ->filter()
            ->implode('；') ?: '-';
    }

    private static function rowDetailsHtml(Order $record): HtmlString
    {
        $record->loadMissing(['items.productVariant', 'shippingCarrier']);

        $items = nl2br(e(static::orderItemsSummary($record)));
        $status = e(app(OrderStatusPresenter::class)->label($record->status));
        $payment = e($record->payment_status ?: '-');
        $carrier = e($record->shippingCarrier?->name ?: '-');
        $trackingNumber = e($record->tracking_number ?: '-');
        $trackingUrl = e($record->tracking_url ?: '-');
        $trackingNumberValue = e($record->tracking_number ?: '');
        $trackingUrlValue = e($record->tracking_url ?: '');
        $address = e($record->shipping_address ?: trim(implode(' ', array_filter([
            $record->shipping_province,
            $record->shipping_city,
            $record->shipping_district,
            $record->shipping_street,
            $record->shipping_detail,
        ]))) ?: '-');
        $contactName = e($record->contact_name ?: '-');
        $contactPhone = e($record->contact_phone ?: '-');
        $contactEmail = e($record->contact_email ?: '-');
        $customerNote = nl2br(e($record->customer_note ?: '-'));
        $digitalContent = nl2br(e($record->digital_delivery_content ?: '-'));
        $digitalCode = e($record->digital_delivery_code ?: '-');
        $action = e(route('admin.orders.quick-shipping', $record, absolute: false));
        $csrf = e(csrf_token());
        $carrierOptions = \App\Models\ShippingCarrier::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (\App\Models\ShippingCarrier $carrier): string => '<option value="'.e((string) $carrier->id).'"'.((int) $record->shipping_carrier_id === (int) $carrier->id ? ' selected' : '').'>'.e($carrier->name).'</option>')
            ->implode('');

        return new HtmlString(<<<HTML
            <div class="shopweb-order-submenu" data-shopweb-order-submenu>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;padding:14px 18px 14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;border-left:3px solid #94a3b8;color:#0f172a;font-size:13px;line-height:1.6;">
                    <div style="display:grid;grid-template-columns:92px minmax(0,1fr);gap:6px 10px;">
                        <strong style="color:#475569;">购买商品</strong>
                        <div style="word-break:break-word;">{$items}</div>
                        <strong style="color:#475569;">订单状态</strong>
                        <div>{$status}</div>
                        <strong style="color:#475569;">付款状态</strong>
                        <div>{$payment}</div>
                    </div>
                    <div style="display:grid;grid-template-columns:82px minmax(0,1fr);gap:6px 10px;">
                        <strong style="color:#475569;">联系人</strong>
                        <div style="word-break:break-word;">{$contactName}</div>
                        <strong style="color:#475569;">电话</strong>
                        <div style="word-break:break-all;">{$contactPhone}</div>
                        <strong style="color:#475569;">邮箱</strong>
                        <div style="word-break:break-all;">{$contactEmail}</div>
                        <strong style="color:#475569;">收货地址</strong>
                        <div style="word-break:break-word;">{$address}</div>
                    </div>
                    <div>
                        <form method="POST" action="{$action}" data-shopweb-row-form style="display:grid;grid-template-columns:82px minmax(0,1fr);gap:8px 10px;" onclick="event.stopPropagation();">
                            <input type="hidden" name="_token" value="{$csrf}">
                            <strong style="color:#475569;">物流</strong>
                            <select name="shipping_carrier_id" aria-label="物流" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;">
                                <option value="">不选择</option>
                                {$carrierOptions}
                            </select>
                            <strong style="color:#475569;">物流单号</strong>
                            <input name="tracking_number" aria-label="物流单号" value="{$trackingNumberValue}" placeholder="-" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;" />
                            <strong style="color:#475569;">物流链接</strong>
                            <input name="tracking_url" aria-label="物流链接" value="{$trackingUrlValue}" placeholder="-" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;" />
                            <strong style="color:#475569;">备注</strong>
                            <input name="admin_note" aria-label="备注" value="后台列表更新物流信息" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;" />
                            <span></span>
                            <button type="submit" style="justify-self:start;border:1px solid #94a3b8;border-radius:6px;background:#fff;padding:6px 12px;color:#0f172a;cursor:pointer;">更新物流</button>
                        </form>
                    </div>
                    <div style="grid-column:1 / -1;display:grid;grid-template-columns:92px minmax(0,1fr);gap:6px 10px;border-top:1px solid #e2e8f0;padding-top:10px;">
                        <strong style="color:#475569;">客户备注</strong>
                        <div style="word-break:break-word;">{$customerNote}</div>
                        <strong style="color:#475569;">线上交付</strong>
                        <div style="word-break:break-word;">{$digitalContent}</div>
                        <strong style="color:#475569;">交付码</strong>
                        <div style="word-break:break-all;">{$digitalCode}</div>
                    </div>
                </div>
            </div>
        HTML);
    }

    private static function rowTriggerHtml(Order $record): HtmlString
    {
        $orderNumber = e($record->order_number);
        $details = static::rowDetailsHtml($record)->toHtml();

        return new HtmlString(<<<HTML
            <span data-shopweb-order-trigger style="display:block;font-weight:600;color:#0f172a;">{$orderNumber}</span>
            <template data-shopweb-order-template>{$details}</template>
            <script>
                if (! window.shopwebOrderRowToggleBound) {
                    window.shopwebOrderRowToggleBound = true;
                    document.addEventListener('click', function (event) {
                        if (event.target.closest('a,button,input,select,textarea,label,[role="button"],[data-shopweb-row-form]')) {
                            return;
                        }

                        var trigger = event.target.closest('[data-shopweb-order-trigger]');
                        var row = trigger ? trigger.closest('tr') : event.target.closest('tr');
                        if (! row || ! row.querySelector('[data-shopweb-order-template]')) {
                            return;
                        }

                        var next = row.nextElementSibling;
                        if (next && next.dataset.shopwebOrderExpanded === 'true') {
                            next.remove();
                            row.classList.remove('shopweb-order-row-open');
                            return;
                        }

                        document.querySelectorAll('tr[data-shopweb-order-expanded="true"]').forEach(function (item) {
                            item.previousElementSibling && item.previousElementSibling.classList.remove('shopweb-order-row-open');
                            item.remove();
                        });

                        var template = row.querySelector('[data-shopweb-order-template]');
                        var expanded = document.createElement('tr');
                        expanded.dataset.shopwebOrderExpanded = 'true';
                        var cell = document.createElement('td');
                        cell.colSpan = row.children.length;
                        cell.style.padding = '0';
                        cell.innerHTML = template.innerHTML;
                        expanded.appendChild(cell);
                        row.insertAdjacentElement('afterend', expanded);
                        row.classList.add('shopweb-order-row-open');
                    });
                }
            </script>
        HTML);
    }

    private static function productStatusLabel(?string $status): string
    {
        return Product::statusOptions()[$status ?: ''] ?? ($status ?: '-');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['items.productVariant']))
            ->columns([
                TextColumn::make('order_number')
                    ->label('订单号')
                    ->state(fn (Order $record): HtmlString => static::rowTriggerHtml($record))
                    ->html()
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['order_number'], $search))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('用户昵称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $userQuery) => RegexSearch::where($userQuery, ['name'], $search))),
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
                TextColumn::make('created_at')->label('创建')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
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
                    ->label('标记待收货')
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
                        Textarea::make('admin_note')->label('特殊原因备注')->required()->rows(4)->helperText('后台直接完成会跳过用户确认收货，请填写用户可见的特殊原因。'),
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
