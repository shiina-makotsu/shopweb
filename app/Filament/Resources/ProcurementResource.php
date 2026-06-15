<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ProcurementResource\Pages\CreateProcurement;
use App\Filament\Resources\ProcurementResource\Pages\EditProcurement;
use App\Filament\Resources\ProcurementResource\Pages\ListProcurements;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ProcurementService;
use App\Services\WarehouseService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Procurement::class;
    protected static string $permissionArea = 'procurement';
    protected static ?string $navigationLabel = '采购';
    protected static ?string $modelLabel = '采购';
    protected static ?string $pluralModelLabel = '采购商品';
    protected static string|\UnitEnum|null $navigationGroup = '仓库';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('采购信息')->schema([
                Select::make('product_id')
                    ->label('被采购的预售商品')
                    ->options(fn (): array => Product::query()
                        ->where('status', Product::STATUS_PRESALE)
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')->label('采购名称')->required()->maxLength(255),
                Select::make('warehouse_id')
                    ->label('入库仓库')
                    ->options(fn (): array => Warehouse::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => Warehouse::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->value('id'))
                    ->required(),
                TextInput::make('quantity')->label('采购数量')->numeric()->minValue(0)->default(0)->required(),
                \App\Support\MoneyInput::currencyAmountSection(TextInput::make('purchase_amount_cents')->label('采购金额')->default(0)->required(), label: '采购金额'),
                \App\Support\MoneyInput::currencyAmountSection(TextInput::make('shipping_amount_cents')->label('运输成本')->default(0), label: '运输成本'),
                Select::make('shipping_country')->label('海关国家/地区')->options([
                    'CN' => '中国',
                    'JP' => '日本',
                    'KR' => '韩国',
                    'US' => '美国',
                    'GB' => '英国',
                    'FR' => '法国',
                    'DE' => '德国',
                    'AU' => '澳大利亚',
                    'OTHER' => '其他',
                ])->searchable(),
                TextInput::make('customs_tax_rate')->label('海关税率')->numeric()->step('0.0001')->helperText('国家/地区留空时按内地进货处理，不计算海关税；填写国家/地区且税率留空或 0 时，按该国家/地区预设税率计算。'),
                TextInput::make('international_tracking_number')->label('国际物流订单号')->maxLength(255),
                TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500)->columnSpanFull(),
                Select::make('status')->label('状态')->options([
                    Procurement::STATUS_DRAFT => '草稿',
                    Procurement::STATUS_INCOMING => '进货中',
                    Procurement::STATUS_RECEIVED => '已到货',
                    Procurement::STATUS_CANCELLED => '已取消',
                ])->default(Procurement::STATUS_INCOMING)->required(),
                Textarea::make('note')->label('采购说明')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('采购名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name', 'international_tracking_number', 'note'], $search))
                    ->sortable(),
                TextColumn::make('product.title')->label('原商品')->sortable(),
                TextColumn::make('incomingProduct.title')->label('进货中商品')->limit(32)->toggleable(),
                TextColumn::make('warehouse.name')->label('入库仓库')->toggleable(),
                TextColumn::make('quantity')->label('数量')->sortable(),
                TextColumn::make('purchase_amount_cents')->label('采购金额')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('shipping_amount_cents')->label('运输成本')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('customs_tax_cents')->label('海关税')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('international_tracking_number')->label('国际物流订单号')->searchable()->toggleable(),
                TextColumn::make('status')->label('状态')->badge(),
                TextColumn::make('received_at')->label('入库时间')->dateTime('Y-m-d H:i')->toggleable(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('sync')
                    ->label('同步进货商品/成本')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(fn (Procurement $record) => app(ProcurementService::class)->syncProcurement($record)),
                Action::make('allocate')
                    ->label('分配预售用户')
                    ->icon(Heroicon::OutlinedUsers)
                    ->form([
                        Select::make('order_item_id')
                            ->label('预售订单项')
                            ->options(fn (Procurement $record): array => self::presaleOrderItemOptions($record))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        TextInput::make('allocated_quantity')
                            ->label('本次分配数量')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Get $get): int => self::presaleOrderItemQuantity((int) $get('order_item_id')))
                            ->helperText(fn (Get $get): string => '该订单项已买 '.self::presaleOrderItemQuantity((int) $get('order_item_id')).' 件')
                            ->required(),
                    ])
                    ->action(fn (Procurement $record, array $data) => app(ProcurementService::class)->syncAllocations($record, [[
                        'order_item_id' => $data['order_item_id'],
                        'allocated_quantity' => $data['allocated_quantity'],
                    ]])),
                Action::make('receive')
                    ->label('确认入库')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('warehouse_note')
                            ->label('入库备注')
                            ->rows(3)
                            ->helperText('货物到达仓库后确认入库，采购状态会变为已入库，并自动增加仓库数量。'),
                    ])
                    ->visible(fn (Procurement $record): bool => AdminAccess::canAction('procurement.receive') && $record->status !== Procurement::STATUS_RECEIVED && $record->status !== Procurement::STATUS_CANCELLED)
                    ->action(fn (Procurement $record, array $data) => app(WarehouseService::class)->receiveProcurement($record, auth()->user(), $data['warehouse_note'] ?? null)),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function presaleOrderItemOptions(Procurement $procurement): array
    {
        return OrderItem::query()
            ->with(['order.user'])
            ->where('product_id', $procurement->product_id)
            ->where('product_status', Product::STATUS_PRESALE)
            ->whereHas('order', fn (Builder $query): Builder => $query->whereIn('status', [
                \App\Models\Order::STATUS_PENDING_SHIPMENT,
                \App\Models\Order::STATUS_INCOMING,
            ]))
            ->get()
            ->mapWithKeys(fn (OrderItem $item): array => [
                $item->id => sprintf(
                    '%s / %s / 已买 %d 件 / 订单 %s',
                    $item->order->user?->displayName() ?? '-',
                    $item->order->user?->public_id ?? '-',
                    $item->quantity,
                    $item->order->order_number,
                ),
            ])
            ->all();
    }

    private static function presaleOrderItemQuantity(int $orderItemId): int
    {
        if ($orderItemId <= 0) {
            return 1;
        }

        return (int) (OrderItem::query()->whereKey($orderItemId)->value('quantity') ?? 1);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcurements::route('/'),
            'create' => CreateProcurement::route('/create'),
            'edit' => EditProcurement::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $data['shipping_country'] = blank($data['shipping_country'] ?? null) ? null : strtoupper((string) $data['shipping_country']);
        $data['customs_tax_rate'] = blank($data['customs_tax_rate'] ?? null) ? 0 : (float) $data['customs_tax_rate'];

        return $data;
    }
}
