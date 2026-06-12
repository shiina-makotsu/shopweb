<?php

namespace App\Filament\Pages;

use App\Models\ProductVariant;
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
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
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
                                $variant->id => ($variant->product?->title ?? '商品').' / '.$variant->sku.' / '.$variant->specLabel(),
                            ])
                            ->all()),
                    Toggle::make('discount_enabled')
                        ->label('启用折扣')
                        ->default(false)
                        ->live()
                        ->helperText('默认关闭。关闭后保存会清空所选 SKU 的折扣价和折扣时间。'),
                    MoneyInput::cents(
                        TextInput::make('discount_price_cents')
                            ->label('折扣价（元）')
                            ->required(fn (Get $get): bool => (bool) $get('discount_enabled'))
                            ->minValue(0),
                        true,
                    )->visible(fn (Get $get): bool => (bool) $get('discount_enabled')),
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

        ProductVariant::query()
            ->whereIn('id', $ids)
            ->update($discountEnabled
                ? [
                    'discount_price_cents' => (int) $state['discount_price_cents'],
                    'discount_starts_at' => $state['discount_starts_at'] ?? null,
                    'discount_ends_at' => $state['discount_ends_at'] ?? null,
                ]
                : [
                    'discount_price_cents' => null,
                    'discount_starts_at' => null,
                    'discount_ends_at' => null,
                ]);

        Notification::make()
            ->title($discountEnabled ? '商品折扣已保存' : '商品折扣已关闭')
            ->body('已更新 '.count($ids).' 个 SKU。')
            ->success()
            ->send();
    }
}
