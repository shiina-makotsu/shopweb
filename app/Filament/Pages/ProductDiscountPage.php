<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Services\StorefrontCache;
use App\Support\AdminAccess;
use App\Support\MoneyInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductDiscountPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '商品折扣';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;
    protected static ?int $navigationSort = 22;
    protected static ?string $slug = 'product-discounts';
    protected string $view = 'filament.pages.product-discount-page';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $discountRows = [];

    public function mount(): void
    {
        $this->form->fill();
        $this->syncDiscountRows();
    }

    public function getTitle(): string
    {
        return '商品折扣';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('coupons');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('批量设置折扣')
                    ->description('商品创建/编辑页只维护 SKU 基础价格；折扣价和折扣时间统一在这里开启、关闭和批量更新。')
                    ->schema([
                    Select::make('variant_ids')
                        ->label('选择 SKU')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(fn (): array => ProductVariant::query()
                            ->with('product')
                            ->where('is_active', true)
                            ->latest()
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (ProductVariant $variant): array => [
                                $variant->id => ($variant->product?->title ?? '商品').' / '.$this->statusLabel($variant->product?->status).' / '.$variant->sku.' / '.$variant->specLabel(),
                            ])
                            ->all()),
                    Toggle::make('discount_enabled')
                        ->label('启用折扣')
                        ->default(false)
                        ->live()
                        ->helperText('默认关闭。关闭后保存会清空所选 SKU 的折扣价和折扣时间。'),
                    ...collect(MoneyInput::convertedCents(
                        TextInput::make('discount_price_cents')
                            ->label('折扣价')
                            ->required(fn (Get $get): bool => (bool) $get('discount_enabled'))
                            ->minValue(0),
                        true,
                    ))
                        ->map(fn ($component) => $component->visible(fn (Get $get): bool => (bool) $get('discount_enabled')))
                        ->all(),
                    DateTimePicker::make('discount_starts_at')
                        ->label('折扣开始')
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => (bool) $get('discount_enabled')),
                    DateTimePicker::make('discount_ends_at')
                        ->label('折扣结束')
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => (bool) $get('discount_enabled')),
                ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $ids = collect($state['variant_ids'] ?? [])->map(fn ($id): int => (int) $id)->all();

        $discountEnabled = (bool) ($state['discount_enabled'] ?? false);
        $productIds = [];

        ProductVariant::query()
            ->whereIn('id', $ids)
            ->get()
            ->each(function (ProductVariant $variant) use ($discountEnabled, $state, &$productIds): void {
                $variant->fill($discountEnabled ? [
                    'discount_price_cents' => (int) $state['discount_price_cents'],
                    'discount_starts_at' => $state['discount_starts_at'] ?? null,
                    'discount_ends_at' => $state['discount_ends_at'] ?? null,
                ] : [
                    'discount_price_cents' => null,
                    'discount_starts_at' => null,
                    'discount_ends_at' => null,
                ]);

                $variant->save();
                $variant->product?->touch();
                $productIds[] = (int) $variant->product_id;
            });

        if ($productIds !== []) {
            app(StorefrontCache::class)->clear();
        }

        $this->syncDiscountRows();

        Notification::make()
            ->title($discountEnabled ? '商品折扣已保存' : '商品折扣已关闭')
            ->body('已更新 '.count($ids).' 个 SKU。')
            ->success()
            ->send();
    }

    public function updateDiscount(int $variantId): void
    {
        $row = $this->discountRows[$variantId] ?? [];
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
        $priceCents = MoneyInput::toCents($row['discount_price'] ?? null);

        if ($priceCents <= 0) {
            Notification::make()
                ->title('折扣价必须大于 0')
                ->danger()
                ->send();

            return;
        }

        $variant->forceFill(['discount_price_cents' => $priceCents])->save();
        $variant->product?->touch();
        app(StorefrontCache::class)->clear();
        $this->syncDiscountRows();

        Notification::make()
            ->title('折扣价已更新')
            ->body($variant->product?->title.' / '.$variant->displayName().'：'.Money::format($priceCents))
            ->success()
            ->send();
    }

    public function cancelDiscount(int $variantId): void
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);
        $variant->forceFill([
            'discount_price_cents' => null,
            'discount_starts_at' => null,
            'discount_ends_at' => null,
        ])->save();
        $variant->product?->touch();
        app(StorefrontCache::class)->clear();
        $this->syncDiscountRows();

        Notification::make()
            ->title('折扣已取消')
            ->body($variant->product?->title.' / '.$variant->displayName())
            ->success()
            ->send();
    }

    public function discountedVariants()
    {
        return ProductVariant::query()
            ->with('product')
            ->whereNotNull('discount_price_cents')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function statusLabel(?string $status): string
    {
        return Product::statusOptions()[$status] ?? (string) ($status ?: '-');
    }

    private function syncDiscountRows(): void
    {
        $this->discountRows = $this->discountedVariants()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => [
                    'discount_price' => MoneyInput::fromCents($variant->discount_price_cents),
                ],
            ])
            ->all();
    }
}
