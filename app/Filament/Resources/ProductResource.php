<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\MediaPath;
use App\Support\CurrencyUnit;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
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
                TextInput::make('slug')
                    ->label('Slug')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('status', $get('status') ?: Product::STATUS_DRAFT))
                    ->helperText('可留空；保存时会自动生成可访问的商品路径。同一状态内不能重复，不同状态允许相同 slug。'),
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
                ...MoneyInput::convertedCents(TextInput::make('shipping_extra_fee_cents')->label('额外邮费')->helperText('可为空；体积/重量超额商品可填写追加邮费，结算时叠加仓库基础邮费。')),
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
                Select::make('variant_price_currency_code')
                    ->label('SKU 默认货币')
                    ->options(CurrencyUnit::currencyOptions())
                    ->default(fn (): string => CurrencyUnit::baseCurrency())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (callable $set, ?string $state): void {
                        $set('variant_price_currency_unit', CurrencyUnit::defaultUnit($state));
                    }),
                Select::make('variant_price_currency_unit')
                    ->label('SKU 默认金额单位')
                    ->options(fn (Get $get): array => CurrencyUnit::unitOptions($get('variant_price_currency_code') ?: CurrencyUnit::baseCurrency()))
                    ->default(fn (): string => CurrencyUnit::baseUnit())
                    ->dehydrated(false),
                Repeater::make('variants')->label('SKU 规格')
                    ->addActionLabel('添加规格值')
                    ->relationship()
                    ->schema([
                        Section::make('SKU 基础')
                            ->schema([
                                TextInput::make('sku')->label('SKU')->required()->maxLength(255),
                                TextInput::make('spec_name')
                                    ->label('规格参数名')
                                    ->placeholder('例如 10片装、20mg 常规装')
                                    ->helperText('前台优先显示这个名称；不填时才使用右侧规格值生成展示名。')
                                    ->maxLength(255),
                            ])
                            ->columns(1)
                            ->columnSpan(1),
                        KeyValue::make('specs')
                            ->label('规格值')
                            ->keyLabel('规格名')
                            ->valueLabel('规格值')
                            ->addActionLabel('添加规格')
                            ->helperText('填写该 SKU 的规格明细，例如 mg=20、片=10。')
                            ->columnSpan(1),
                        Section::make('SKU 图片')
                            ->schema([
                                TextInput::make('image_path')
                                    ->label('图片链接')
                                    ->placeholder('https://example.com/sku.jpg 或 products/sku.jpg')
                                    ->helperText('粘贴链接，或使用右侧 + 选择资源库文件/上传。')
                                    ->live(debounce: 500)
                                    ->maxLength(2048)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : trim($state)),
                                static::imageAssetPicker('image_path', '+'),
                                Placeholder::make('image_preview')
                                    ->label('')
                                    ->content(fn (Get $get): HtmlString => static::compactImagePreviewHtml($get('image_path')))
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(1),
                        Section::make('价格与库存')
                            ->schema([
                                ...MoneyInput::convertedCents(
                                    TextInput::make('price_cents')->label('价格')->required(),
                                    defaultCurrencyField: '../../variant_price_currency_code',
                                    defaultUnitField: '../../variant_price_currency_unit',
                                    includeControls: false,
                                ),
                                TextInput::make('stock')
                                    ->label('库存')
                                    ->numeric()
                                    ->required(fn (Get $get): bool => $get('../../status') !== Product::STATUS_PRESALE)
                                    ->default(0)
                                    ->visible(fn (Get $get): bool => $get('../../status') !== Product::STATUS_PRESALE)
                                    ->helperText(fn (Get $get): string => $get('../../fulfillment_type') === Product::FULFILLMENT_ONLINE
                                        ? '线上交付前台按不限库存处理。'
                                        : '物流/当面交付会按库存限制下单。'),
                                TextInput::make('low_stock_threshold')
                                    ->label('低库存阈值')
                                    ->numeric()
                                    ->default(5)
                                    ->visible(fn (Get $get): bool => $get('../../status') !== Product::STATUS_PRESALE),
                                Toggle::make('is_active')->label('启用')->default(true),
                            ])
                            ->columns(1)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): ?string => filled($state['spec_name'] ?? null)
                        ? (string) $state['spec_name']
                        : (filled($state['sku'] ?? null) ? (string) $state['sku'] : null))
                    ->helperText('每一项就是一个可售 SKU，可维护多个规格名/规格值、独立基础价格、库存和 SKU 图片；折扣价与折扣时间请到交易菜单的“商品折扣”页面设置。')
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('商品媒体')->schema([
                Repeater::make('media')->label('图片 / 视频')
                    ->addActionLabel('添加图片 / 视频')
                    ->relationship()
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => filled($data['path'] ?? null) ? $data : null)
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): ?array => filled($data['path'] ?? null) ? $data : null)
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
                            ->helperText('点击“添加图片 / 视频”新增一行；可直接粘贴外部 URL、/uploads/... 路径或 public/uploads 下的相对路径，也可在旁边从资源库选择或点击 + 上传。')
                            ->live(debounce: 500)
                            ->maxLength(2048)
                            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : trim($state)),
                        static::mediaAssetPicker('path'),
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
                ...MoneyInput::convertedCents(TextInput::make('crowdfunding_goal_cents')->label('筹款目标'), true),
                Textarea::make('crowdfunding_reward')->label('正式发布奖励')->rows(3)->columnSpanFull(),
                Repeater::make('priceVoteOptions')->label('价格区间')
                    ->relationship()
                    ->schema([
                        TextInput::make('label')->label('标签')->required(),
                        ...MoneyInput::convertedCents(TextInput::make('min_cents')->label('最低'), true),
                        ...MoneyInput::convertedCents(TextInput::make('max_cents')->label('最高'), true),
                        TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                        Toggle::make('is_active')->label('启用')->default(true),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])->visible(fn (Get $get): bool => $get('status') === Product::STATUS_CONCEPT)->columnSpanFull(),
        ]);
    }

    private static function imageAssetPicker(string $targetField, string $label): Select
    {
        return Select::make($targetField.'_asset_picker')
            ->label($label)
            ->placeholder('选择已有图片，或点击 + 上传')
            ->helperText('选择后会自动填入左侧图片路径输入框。')
            ->searchable()
            ->preload()
            ->live()
            ->dehydrated(false)
            ->options(fn (): array => static::imageOptions())
            ->getSearchResultsUsing(fn (string $search): array => static::imageOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => static::assetOptionLabel($value))
            ->afterStateUpdated(function (?string $state, callable $set) use ($targetField): void {
                if (filled($state)) {
                    $set($targetField, $state);
                    $set($targetField.'_asset_picker', null);
                }
            })
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传图片')
                    ->helperText('上传图片，或填写下方外部图片 URL。')
                    ->disk('public_uploads')
                    ->directory('products/skus')
                    ->image()
                    ->maxSize(5120),
                TextInput::make('external_url')
                    ->label('外部图片 URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $asset = MediaAsset::createImageFromUploadOrUrl($data, MediaAsset::USAGE_PRODUCT);

                return $asset->path;
            });
    }

    private static function mediaAssetPicker(string $targetField): Select
    {
        return Select::make($targetField.'_asset_picker')
            ->label('资源库 / 上传文件')
            ->placeholder('选择已有图片/视频，或点击 + 上传')
            ->helperText('选择后会自动填入左侧媒体路径输入框。')
            ->searchable()
            ->preload()
            ->live()
            ->dehydrated(false)
            ->options(fn (): array => static::mediaOptions())
            ->getSearchResultsUsing(fn (string $search): array => static::mediaOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => static::assetOptionLabel($value))
            ->afterStateUpdated(function (?string $state, callable $set) use ($targetField): void {
                if (blank($state)) {
                    return;
                }

                $set($targetField, $state);

                $asset = MediaAsset::query()->where('path', $state)->first();

                if (! $asset) {
                    return;
                }

                if ($asset->isVideo()) {
                    $set('media_kind', ProductMedia::KIND_VIDEO);
                } elseif ($asset->isImage()) {
                    $set('media_kind', ProductMedia::KIND_IMAGE);
                }

                if (filled($asset->mime_type)) {
                    $set('mime_type', $asset->mime_type);
                }

                if (filled($asset->alt)) {
                    $set('alt', $asset->alt);
                }
            })
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传图片/视频')
                    ->helperText('上传商品图片或视频，或填写下方外部 URL。')
                    ->disk('public_uploads')
                    ->directory('products/media')
                    ->acceptedFileTypes(static::productMediaAcceptedFileTypes())
                    ->maxSize(51200),
                TextInput::make('external_url')
                    ->label('外部图片/视频 URL')
                    ->url()
                    ->maxLength(2048),
                Select::make('media_kind')
                    ->label('媒体类型')
                    ->options([
                        ProductMedia::KIND_IMAGE => '图片',
                        ProductMedia::KIND_VIDEO => '视频',
                    ])
                    ->default(ProductMedia::KIND_IMAGE)
                    ->required(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $asset = static::createProductMediaAssetFromUploadOrUrl($data);

                return $asset->path;
            });
    }

    /**
     * @return array<string, string>
     */
    private static function imageOptions(?string $search = null): array
    {
        return static::assetOptionQuery($search)
            ->where(function (Builder $query): void {
                $query->where('mime_type', 'like', 'image/%')
                    ->orWhereNull('mime_type');
            })
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => static::assetOptionText($asset)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function mediaOptions(?string $search = null): array
    {
        return static::assetOptionQuery($search)
            ->where(function (Builder $query): void {
                $query->where('mime_type', 'like', 'image/%')
                    ->orWhere('mime_type', 'like', 'video/%')
                    ->orWhereNull('mime_type');
            })
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => static::assetOptionText($asset)])
            ->all();
    }

    private static function assetOptionQuery(?string $search = null): Builder
    {
        return MediaAsset::query()
            ->when($search, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50);
    }

    private static function assetOptionLabel($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $asset = MediaAsset::query()->where('path', $value)->first();

        return $asset ? static::assetOptionText($asset) : (string) $value;
    }

    private static function assetOptionText(MediaAsset $asset): string
    {
        $name = $asset->name ?: basename((string) $asset->path);

        return $asset->path ? "{$name} · {$asset->path}" : $name;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function createProductMediaAssetFromUploadOrUrl(array $data): MediaAsset
    {
        $path = $data['path'] ?? null;
        $path = is_array($path) ? reset($path) : $path;

        if (! is_string($path) || blank($path)) {
            $path = MediaAsset::pathFromUploadOrUrl($data);
        }

        $isExternal = MediaAsset::isExternalUrl($path);

        return MediaAsset::query()->create([
            'name' => $data['name'] ?? MediaAsset::nameFromPath($path),
            'path' => $path,
            'disk' => $isExternal ? 'external' : 'public_uploads',
            'mime_type' => $isExternal ? static::externalProductMediaMimeType($path, $data['media_kind'] ?? null) : ($data['mime_type'] ?? null),
            'alt' => $data['alt'] ?? null,
            'usage' => MediaAsset::USAGE_PRODUCT,
        ]);
    }

    private static function externalProductMediaMimeType(string $path, ?string $kind = null): string
    {
        $extension = Str::lower(pathinfo((string) parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));

        if ($kind === ProductMedia::KIND_VIDEO || in_array($extension, ['mp4', 'webm', 'mov', 'm4v', 'avi'], true)) {
            return 'video/external';
        }

        return 'image/external';
    }

    /**
     * @return array<int, string>
     */
    private static function productMediaAcceptedFileTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
            'video/quicktime',
        ];
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

    private static function compactImagePreviewHtml(?string $path): HtmlString
    {
        $url = MediaPath::url($path);

        if (! $url) {
            return new HtmlString('');
        }

        $escapedUrl = e($url);

        return new HtmlString(<<<HTML
<img src="{$escapedUrl}" alt="图片预览" style="display:block;width:100%;max-width:260px;max-height:180px;object-fit:contain;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;">
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

    private static function quickEditDetailsHtml(Product $record): HtmlString
    {
        $record->loadMissing('variants');

        $action = e(route('admin.products.quick-update', $record, absolute: false));
        $csrf = e(csrf_token());
        $title = e($record->title);
        $featuredChecked = $record->is_featured ? ' checked' : '';
        $statusOptions = collect(Product::statusOptions())
            ->map(fn (string $label, string $value): string => '<option value="'.e($value).'"'.($record->status === $value ? ' selected' : '').'>'.e($label).'</option>')
            ->implode('');
        $variants = $record->variants
            ->map(function (ProductVariant $variant): string {
                $id = (int) $variant->id;
                $sku = e($variant->sku ?: ('SKU #'.$id));
                $specName = e((string) $variant->spec_name);
                $price = e(number_format(((int) $variant->price_cents) / 100, 2, '.', ''));
                $stock = e((string) (int) $variant->stock);

                return <<<HTML
                    <div class="shopweb-product-variant-row" style="display:grid;grid-template-columns:minmax(110px,1fr) minmax(130px,1.1fr) 90px 80px;gap:8px;align-items:center;border-top:1px solid #e2e8f0;padding-top:8px;">
                        <input type="hidden" name="variants[{$id}][id]" value="{$id}">
                        <div class="shopweb-product-quick-sku" style="font-weight:600;color:#334155;word-break:break-word;">{$sku}</div>
                        <input class="shopweb-product-quick-input" name="variants[{$id}][spec_name]" value="{$specName}" placeholder="规格参数名" aria-label="{$sku} 规格参数名" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;min-width:0;" />
                        <input class="shopweb-product-quick-input" name="variants[{$id}][price_cents]" value="{$price}" aria-label="{$sku} 价格" inputmode="decimal" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;min-width:0;" />
                        <input class="shopweb-product-quick-input" name="variants[{$id}][stock]" value="{$stock}" aria-label="{$sku} 库存" inputmode="numeric" style="min-height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;min-width:0;" />
                    </div>
                HTML;
            })
            ->implode('');

        if ($variants === '') {
            $variants = '<p style="margin:0;color:#64748b;">暂无 SKU，可进入详情页添加。</p>';
        }

        return new HtmlString(<<<HTML
            <div class="shopweb-product-submenu" data-shopweb-product-submenu>
                <form class="shopweb-product-quick-form" method="POST" action="{$action}" data-shopweb-product-row-form onclick="event.stopPropagation();" style="display:grid;gap:12px;padding:14px 18px 14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;border-left:3px solid #94a3b8;color:#0f172a;font-size:13px;line-height:1.6;">
                    <input type="hidden" name="_token" value="{$csrf}">
                    <div style="display:grid;grid-template-columns:minmax(220px,1.3fr) 150px 110px auto;gap:10px;align-items:end;">
                        <label class="shopweb-product-quick-label" style="display:grid;gap:4px;font-weight:600;color:#475569;">商品标题
                            <input class="shopweb-product-quick-input" name="title" value="{$title}" required style="min-height:34px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;min-width:0;" />
                        </label>
                        <label class="shopweb-product-quick-label" style="display:grid;gap:4px;font-weight:600;color:#475569;">商品状态
                            <select class="shopweb-product-quick-input" name="status" required style="min-height:34px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:4px 8px;color:#0f172a;">
                                {$statusOptions}
                            </select>
                        </label>
                        <label class="shopweb-product-quick-label" style="display:flex;align-items:center;gap:8px;min-height:34px;font-weight:600;color:#475569;">
                            <input type="hidden" name="is_featured" value="0">
                            <input type="checkbox" name="is_featured" value="1"{$featuredChecked}>
                            推荐
                        </label>
                        <button class="shopweb-product-quick-button" type="submit" style="justify-self:start;border:1px solid #94a3b8;border-radius:6px;background:#fff;padding:7px 14px;color:#0f172a;cursor:pointer;">保存快速修改</button>
                    </div>
                    <div style="display:grid;gap:8px;">
                        <div class="shopweb-product-quick-head" style="display:grid;grid-template-columns:minmax(110px,1fr) minmax(130px,1.1fr) 90px 80px;gap:8px;color:#64748b;font-weight:600;">
                            <span>SKU</span>
                            <span>规格参数名</span>
                            <span>价格</span>
                            <span>库存</span>
                        </div>
                        {$variants}
                    </div>
                </form>
            </div>
        HTML);
    }

    private static function quickEditTriggerHtml(Product $record): HtmlString
    {
        $title = e($record->title);
        $details = static::quickEditDetailsHtml($record)->toHtml();

        return new HtmlString(<<<HTML
            <span data-shopweb-product-trigger style="display:block;font-weight:600;color:#0f172a;">{$title}</span>
            <template data-shopweb-product-template>{$details}</template>
            <script>
                if (! window.shopwebProductRowToggleBound) {
                    window.shopwebProductRowToggleBound = true;
                    document.addEventListener('click', function (event) {
                        if (event.target.closest('a,button,input,select,textarea,label,[role="button"],[data-shopweb-product-row-form]')) {
                            return;
                        }

                        var trigger = event.target.closest('[data-shopweb-product-trigger]');
                        var row = trigger ? trigger.closest('tr') : event.target.closest('tr');
                        if (! row || ! row.querySelector('[data-shopweb-product-template]')) {
                            return;
                        }

                        var next = row.nextElementSibling;
                        if (next && next.dataset.shopwebProductExpanded === 'true') {
                            next.remove();
                            row.classList.remove('shopweb-product-row-open');
                            return;
                        }

                        document.querySelectorAll('tr[data-shopweb-product-expanded="true"]').forEach(function (item) {
                            item.previousElementSibling && item.previousElementSibling.classList.remove('shopweb-product-row-open');
                            item.remove();
                        });

                        var template = row.querySelector('[data-shopweb-product-template]');
                        var expanded = document.createElement('tr');
                        expanded.dataset.shopwebProductExpanded = 'true';
                        var cell = document.createElement('td');
                        cell.colSpan = row.children.length;
                        cell.style.padding = '0';
                        cell.innerHTML = template.innerHTML;
                        expanded.appendChild(cell);
                        row.insertAdjacentElement('afterend', expanded);
                        row.classList.add('shopweb-product-row-open');
                    });
                }
            </script>
        HTML);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['variants', 'tags']))
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->state(fn (Product $record): HtmlString => static::quickEditTriggerHtml($record))
                    ->html()
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title'], $search))
                    ->sortable(),
                TextColumn::make('category.name')->label('分类')->sortable(),
                TextColumn::make('tags.name')
                    ->label('标签')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                TextColumn::make('manufacturer.name')->label('制造商')->toggleable(),
                TextColumn::make('status')->label('状态')->formatStateUsing(fn (?string $state): string => Product::statusOptions()[$state] ?? (string) $state)->badge(),
                IconColumn::make('is_featured')->label('推荐')->boolean(),
                TextColumn::make('variants_sum_stock')
                    ->sum('variants', 'stock')
                    ->label('库存')
                    ->formatStateUsing(fn ($state, Product $record): string => $record->hasUnlimitedStock() ? '不限' : (string) (int) $state)
                    ->sortable(),
                TextColumn::make('incoming_quantity')->label('进货数')->toggleable(),
                TextColumn::make('quantityUnit.name')->label('单位')->toggleable(),
                TextColumn::make('updated_at')->label('更新')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(fn (): array => Product::statusOptions()),
                SelectFilter::make('tags')
                    ->label('标签')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('manufacturer_id')
                    ->label('制造商')
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('updated_at')
                    ->label('更新时间')
                    ->schema([
                        DatePicker::make('from')->label('开始日期'),
                        DatePicker::make('until')->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('updated_at', '<=', $date));
                    }),
            ])
            ->recordUrl(null)
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
