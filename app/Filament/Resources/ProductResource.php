<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\MediaPath;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Product::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '商品管理';
    protected static ?string $modelLabel = '商品';
    protected static ?string $pluralModelLabel = '商品管理';
    protected static string|\UnitEnum|null $navigationGroup = '商品';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;
    protected static ?int $navigationSort = 20;

    public static function resolveRecordRouteBinding(int|string $key, ?\Closure $modifyQuery = null): ?Model
    {
        $record = parent::resolveRecordRouteBinding($key, $modifyQuery);

        if ($record || ! ctype_digit((string) $key)) {
            return $record;
        }

        $query = static::getRecordRouteBindingEloquentQuery();

        if ($modifyQuery) {
            $query = $modifyQuery($query) ?? $query;
        }

        return $query->whereKey((int) $key)->first();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('基础信息')->schema([
                Select::make('category_id')->label('分类')->relationship('category', 'name')->searchable()->preload(),
                Select::make('tags')->label('标签')->relationship('tags', 'name')->multiple()->searchable()->preload(),
                Select::make('manufacturer_id')->label('制造商')->relationship('manufacturer', 'name')->searchable()->preload(),
                Select::make('supplier_id')->label('供应商')->relationship('supplier', 'name')->searchable()->preload(),
                TextInput::make('title')->label('标题')->required()->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Slug')->unique(ignoreRecord: true)->helperText('可留空；保存时会自动生成可访问的商品路径。'),
                Textarea::make('summary')->label('简介')->rows(3)->columnSpanFull(),
                RichEditor::make('description')->label('详情')->columnSpanFull(),
                Select::make('status')->label('状态')->required()->options(fn (?Product $record): array => $record?->status === Product::STATUS_SOLD_OUT
                    ? Product::statusOptions()
                    : Product::editableStatusOptions())
                    ->live()
                    ->helperText('售罄由库存自动维护；概念和进货中商品不会进入售罄状态。')
                    ->default(Product::STATUS_DRAFT),
                Select::make('fulfillment_type')->label('交付类型')->required()->options([
                    Product::FULFILLMENT_ONLINE => '线上交付',
                    Product::FULFILLMENT_LOGISTICS => '物流交付',
                    Product::FULFILLMENT_IN_PERSON => '当面交付',
                ])->default(Product::FULFILLMENT_LOGISTICS),
                Select::make('delivery_status_id')->label('交付状态')->relationship('deliveryStatus', 'name')->searchable()->preload(),
                Select::make('quantity_unit_id')->label('数量单位')->relationship('quantityUnit', 'name')->searchable()->preload(),
                MoneyInput::cents(TextInput::make('shipping_extra_fee_cents')->label('额外邮费（元）')->helperText('可为空；体积/重量超额商品可填写追加邮费，结算时叠加仓库基础邮费。')),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_featured')->label('推荐')->default(false),
                Toggle::make('comments_enabled')->label('开启评论')->default(true),
            ])->columns(2)->columnSpanFull(),

            Section::make('进货物流')->description('仅商品状态为“进货中”时显示。国际物流默认对用户隐藏，可在前台用户中逐个或批量开放。')->schema([
                Select::make('source_product_id')->label('来源预售商品')->relationship('sourceProduct', 'title')->searchable()->preload(),
                TextInput::make('incoming_quantity')->label('进货数量')->numeric()->default(0),
                Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500)->columnSpanFull(),
                Textarea::make('incoming_note')->label('进货说明')->rows(3)->columnSpanFull(),
            ])->visible(fn (Get $get): bool => $get('status') === Product::STATUS_INCOMING)->columns(2)->columnSpanFull(),

            Section::make('SKU 与库存')->schema([
                Repeater::make('variants')->label('SKU 规格')
                    ->addActionLabel('添加规格值')
                    ->relationship()
                    ->schema([
                        TextInput::make('sku')->label('SKU')->required()->maxLength(255),
                        KeyValue::make('specs')
                            ->label('规格值')
                            ->keyLabel('规格名')
                            ->valueLabel('规格值')
                            ->addActionLabel('添加规格名/值')
                            ->columnSpanFull(),
                        TextInput::make('image_path')
                            ->label('SKU 图片链接或路径')
                            ->placeholder('https://example.com/sku.jpg 或 products/sku.jpg')
                            ->helperText('可粘贴外部图片 URL、/uploads/... 路径或 public/uploads 下的相对路径。')
                            ->live(debounce: 500)
                            ->maxLength(2048)
                            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : trim($state)),
                        Placeholder::make('image_preview')
                            ->label('SKU 图片预览')
                            ->content(fn (Get $get): HtmlString => static::imagePreviewHtml($get('image_path')))
                            ->html(),
                        MoneyInput::cents(TextInput::make('price_cents')->label('价格（元）')->required()),
                        TextInput::make('stock')->label('库存')->numeric()->required()->default(0),
                        TextInput::make('low_stock_threshold')->label('低库存阈值')->numeric()->default(5),
                        Toggle::make('is_active')->label('启用')->default(true),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): ?string => filled($state['sku'] ?? null) ? (string) $state['sku'] : null)
                    ->helperText('每一项就是一个可售 SKU，可维护多个规格名/规格值、独立基础价格、库存和 SKU 图片；折扣价与折扣时间请到交易菜单的“商品折扣”页面设置。')
                    ->columnSpanFull(),
            ])->columnSpanFull(),

            Section::make('商品媒体')->schema([
                Repeater::make('media')->label('图片 / 视频')
                    ->addActionLabel('添加图片 / 视频')
                    ->relationship()
                    ->schema([
                        Select::make('type')->label('类型')->options([
                            ProductMedia::TYPE_CONCEPT => '概念图',
                            ProductMedia::TYPE_PREVIEW => '预览图',
                        ])->default(ProductMedia::TYPE_PREVIEW)->required(),
                        Select::make('media_kind')->label('媒体类型')->options([
                            ProductMedia::KIND_IMAGE => '图片',
                            ProductMedia::KIND_VIDEO => '视频',
                        ])->default(ProductMedia::KIND_IMAGE)->required()->live(),
                        TextInput::make('path')
                            ->label('图片/视频链接或路径')
                            ->placeholder('https://example.com/image.jpg 或 products/demo.jpg')
                            ->helperText('点击“添加图片 / 视频”新增一行；也可以直接粘贴外部 URL、/uploads/... 路径或 public/uploads 下的相对路径。')
                            ->live(debounce: 500)
                            ->maxLength(2048)
                            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : trim($state))
                            ->required(),
                        Placeholder::make('media_preview')
                            ->label('预览')
                            ->content(fn (Get $get): HtmlString => static::mediaPreviewHtml($get('path'), $get('media_kind'), $get('alt')))
                            ->html(),
                        TextInput::make('mime_type')->label('MIME')->maxLength(100),
                        TextInput::make('alt')->label('Alt 文案'),
                        TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])->columnSpanFull(),

            Section::make('概念商品投票与筹款')->description('仅概念商品会在前台显示购买意愿、价格区间投票和筹款说明。')->schema([
                Toggle::make('crowdfunding_enabled')->label('启用筹款')->default(false),
                MoneyInput::cents(TextInput::make('crowdfunding_goal_cents')->label('筹款目标（元）'), true),
                Textarea::make('crowdfunding_reward')->label('正式发布奖励')->rows(3)->columnSpanFull(),
                Repeater::make('priceVoteOptions')->label('价格区间')
                    ->relationship()
                    ->schema([
                        TextInput::make('label')->label('标签')->required(),
                        MoneyInput::cents(TextInput::make('min_cents')->label('最低（元）'), true),
                        MoneyInput::cents(TextInput::make('max_cents')->label('最高（元）'), true),
                        TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                        Toggle::make('is_active')->label('启用')->default(true),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])->visible(fn (Get $get): bool => $get('status') === Product::STATUS_CONCEPT)->columnSpanFull(),
        ]);
    }

    private static function imagePreviewHtml(?string $path): HtmlString
    {
        $url = MediaPath::url($path);

        if (! $url) {
            return new HtmlString('<p style="margin:0;color:#64748b;font-size:12px;">粘贴图片链接或路径后显示预览。</p>');
        }

        $escapedUrl = e($url);

        return new HtmlString(<<<HTML
<img src="{$escapedUrl}" alt="图片预览" style="width:72px;height:72px;object-fit:cover;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;">
HTML);
    }

    private static function mediaPreviewHtml(?string $path, ?string $kind = ProductMedia::KIND_IMAGE, ?string $alt = null): HtmlString
    {
        $url = MediaPath::url($path);

        if (! $url) {
            return new HtmlString('<p style="margin:0;color:#64748b;font-size:12px;">粘贴图片/视频链接或路径后显示预览。</p>');
        }

        $escapedUrl = e($url);
        $escapedAlt = e($alt ?: '媒体预览');

        if ($kind === ProductMedia::KIND_VIDEO) {
            return new HtmlString(<<<HTML
<video src="{$escapedUrl}" style="width:120px;height:72px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#020617;" muted controls preload="metadata"></video>
HTML);
        }

        return new HtmlString(<<<HTML
<img src="{$escapedUrl}" alt="{$escapedAlt}" style="width:72px;height:72px;object-fit:cover;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;">
HTML);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title'], $search))
                    ->sortable(),
                TextColumn::make('category.name')->label('分类')->sortable(),
                TextColumn::make('manufacturer.name')->label('制造商')->toggleable(),
                TextColumn::make('status')->label('状态')->formatStateUsing(fn (?string $state): string => Product::statusOptions()[$state] ?? (string) $state)->badge(),
                IconColumn::make('is_featured')->label('推荐')->boolean(),
                TextColumn::make('variants_sum_stock')->sum('variants', 'stock')->label('库存')->sortable(),
                TextColumn::make('incoming_quantity')->label('进货数')->toggleable(),
                TextColumn::make('quantityUnit.name')->label('单位')->toggleable(),
                TextColumn::make('updated_at')->label('更新')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->url(fn (Product $record): string => static::getUrl('edit', ['record' => $record->getKey()])),
                Action::make('duplicateIncoming')
                    ->label('生成进货中')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->form([
                        TextInput::make('incoming_quantity')->label('进货数量')->numeric()->required()->minValue(1),
                        TextInput::make('title_suffix')->label('标题后缀')->default('进货中'),
                        Select::make('shipping_carrier_id')->label('物流承运商')->relationship('shippingCarrier', 'name')->searchable()->preload(),
                        TextInput::make('tracking_number')->label('物流单号')->maxLength(255),
                        TextInput::make('tracking_url')->label('物流查询链接')->maxLength(500),
                        Textarea::make('incoming_note')->label('进货说明')->rows(3),
                    ])
                    ->visible(fn (Product $record): bool => $record->status === Product::STATUS_PRESALE)
                    ->action(function (Product $record, array $data): void {
                        $record->load(['variants', 'media']);

                        $incoming = $record->replicate([
                            'slug',
                            'status',
                            'is_featured',
                            'created_at',
                            'updated_at',
                        ]);
                        $suffix = trim((string) ($data['title_suffix'] ?? '进货中')) ?: '进货中';
                        $incoming->fill([
                            'title' => $record->title.' - '.$suffix,
                            'slug' => $record->slug.'-incoming-'.now()->format('YmdHis'),
                            'status' => Product::STATUS_INCOMING,
                            'is_featured' => false,
                            'source_product_id' => $record->id,
                            'incoming_quantity' => (int) $data['incoming_quantity'],
                            'shipping_carrier_id' => $data['shipping_carrier_id'] ?? null,
                            'tracking_number' => $data['tracking_number'] ?? null,
                            'tracking_url' => $data['tracking_url'] ?? null,
                            'incoming_note' => $data['incoming_note'] ?? null,
                        ])->save();

                        foreach ($record->variants as $variant) {
                            /** @var ProductVariant $variant */
                            $incoming->variants()->create([
                                'sku' => $variant->sku.'-IN-'.now()->format('His'),
                                'specs' => $variant->specs,
                                'image_path' => $variant->image_path,
                                'price_cents' => $variant->price_cents,
                                'compare_at_price_cents' => $variant->compare_at_price_cents,
                                'stock' => 0,
                                'low_stock_threshold' => $variant->low_stock_threshold,
                                'is_active' => false,
                            ]);
                        }

                        foreach ($record->media as $media) {
                            $incoming->media()->create($media->only(['type', 'media_kind', 'path', 'mime_type', 'alt', 'sort_order']));
                        }
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
