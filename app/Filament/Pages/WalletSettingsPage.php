<?php

namespace App\Filament\Pages;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\WalletRechargeOption;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Filament\Support\AdminPageTabs;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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

class WalletSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '钱包';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?int $navigationSort = 9;
    protected static ?string $slug = 'wallet-settings';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'recharge_options' => WalletRechargeOption::query()
                ->orderBy('sort_order')
                ->orderBy('amount_cents')
                ->get()
                ->map(fn (WalletRechargeOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'currency_code' => $option->currency_code,
                    'currency_unit' => $option->currency_unit,
                    'amount_cents' => $option->amount_cents,
                    'discount_percent' => $option->discount_percent,
                    'bonus_cents' => $option->bonus_cents,
                    'is_active' => $option->is_active,
                    'sort_order' => $option->sort_order,
                    'coupon_reward_enabled' => $option->coupon_reward_enabled,
                    'coupon_reward_currency_code' => $option->coupon_reward_currency_code,
                    'coupon_reward_currency_unit' => $option->coupon_reward_currency_unit,
                    'coupon_reward_type' => $option->coupon_reward_type,
                    'coupon_reward_value' => $option->coupon_reward_value,
                    'coupon_reward_valid_days' => $option->coupon_reward_valid_days,
                    'coupon_reward_scope' => $option->coupon_reward_scope,
                    'coupon_reward_product_ids' => $option->coupon_reward_product_ids ?: [],
                    'coupon_reward_minimum_order_cents' => $option->coupon_reward_minimum_order_cents,
                    'coupon_reward_quantity' => $option->coupon_reward_quantity,
                    'coupon_reward_usage_limit' => $option->coupon_reward_usage_limit,
                ])
                ->all(),
        ]);
    }

    public function getTitle(): string
    {
        return '钱包';
    }

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(static::tabs(), 'settings');
    }

    public static function tabs(): array
    {
        return [
            'settings' => ['label' => '钱包设置', 'url' => static::getUrl()],
            'redeem_codes' => ['label' => '兑换码管理', 'url' => WalletRedeemCodeResource::getUrl('index')],
        ];
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('payments');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('充值选项')
                    ->description('在这里维护用户前台钱包页显示的充值档位；每个档位可设置折扣付款或赠送到账余额。')
                    ->schema([
                        Repeater::make('recharge_options')
                            ->label('充值选项')
                            ->addActionLabel('添加充值选项')
                            ->reorderable()
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')->label('名称')->maxLength(255),
                                ...MoneyInput::conversionControls('amount_cents', 'currency_code', 'currency_unit', dehydrated: true),
                                TextInput::make('amount_cents')
                                    ->label('充值面额')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->formatStateUsing(fn ($state): ?string => CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1))
                                    ->dehydrateStateUsing(fn ($state, callable $get): int => CurrencyUnit::toSettlementCents(
                                        $state,
                                        CurrencyUnit::normalizeCurrency($get('currency_code') ?: CurrencyUnit::baseCurrency()),
                                        $get('currency_unit') ?: CurrencyUnit::defaultUnit($get('currency_code') ?: CurrencyUnit::baseCurrency()),
                                        CurrencyUnit::exchangeRateFor($get('currency_code') ?: CurrencyUnit::baseCurrency()),
                                    )),
                                TextInput::make('discount_percent')
                                    ->label('付款比例 %')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->helperText('不填表示无折扣；例如填 90 表示按 90% 付款。'),
                                ...MoneyInput::convertedCents(
                                    TextInput::make('bonus_cents')->label('赠送余额')->minValue(0),
                                    controlName: 'bonus_cents',
                                    defaultCurrencyField: 'currency_code',
                                    defaultUnitField: 'currency_unit',
                                    includeControls: false,
                                ),
                                Toggle::make('is_active')->label('启用')->default(true),
                                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                                Section::make('充值赠送优惠券')
                                    ->description('确认该充值订单收款后，系统会自动生成普通优惠码并发放给用户；生成后的优惠码仍在“优惠码”中查看。')
                                    ->schema([
                                        Toggle::make('coupon_reward_enabled')
                                            ->label('启用充值赠券')
                                            ->default(false)
                                            ->live(),
                                        Select::make('coupon_reward_type')
                                            ->label('赠券类型')
                                            ->options([
                                                Coupon::TYPE_FIXED => '固定金额',
                                                Coupon::TYPE_PERCENT => '折扣百分比',
                                            ])
                                            ->default(Coupon::TYPE_FIXED)
                                            ->live()
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')),
                                        ...collect(MoneyInput::conversionControls('coupon_reward_value', 'coupon_reward_currency_code', 'coupon_reward_currency_unit', dehydrated: true))
                                            ->map(fn ($component) => $component->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled') && $get('coupon_reward_type') !== Coupon::TYPE_PERCENT))
                                            ->all(),
                                        TextInput::make('coupon_reward_value')
                                            ->label(fn (Get $get): string => $get('coupon_reward_type') === Coupon::TYPE_PERCENT ? '折扣百分比' : '优惠金额')
                                            ->numeric()
                                            ->minValue(0)
                                            ->helperText('固定金额按所选币种单位录入；折扣百分比填写 1-100。')
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled'))
                                            ->formatStateUsing(function ($state, Get $get): ?string {
                                                if ($state === null || $state === '') {
                                                    return null;
                                                }

                                                return $get('coupon_reward_type') === Coupon::TYPE_PERCENT
                                                    ? (string) $state
                                                    : CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1);
                                            })
                                            ->dehydrateStateUsing(function ($state, Get $get): int {
                                                if ($get('coupon_reward_type') === Coupon::TYPE_PERCENT) {
                                                    return max(0, min(100, (int) $state));
                                                }

                                                $currency = CurrencyUnit::normalizeCurrency($get('coupon_reward_currency_code') ?: CurrencyUnit::baseCurrency());

                                                return CurrencyUnit::toSettlementCents(
                                                    $state,
                                                    $currency,
                                                    $get('coupon_reward_currency_unit') ?: CurrencyUnit::defaultUnit($currency),
                                                    CurrencyUnit::exchangeRateFor($currency),
                                                );
                                            }),
                                        TextInput::make('coupon_reward_valid_days')
                                            ->label('有效天数')
                                            ->numeric()
                                            ->minValue(1)
                                            ->helperText('不填则永久有效。')
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')),
                                        Select::make('coupon_reward_scope')
                                            ->label('适用范围')
                                            ->options(Coupon::scopeOptions())
                                            ->default(Coupon::SCOPE_GLOBAL)
                                            ->live()
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')),
                                        Select::make('coupon_reward_product_ids')
                                            ->label('指定商品')
                                            ->options(fn (): array => Product::query()->orderBy('title')->limit(200)->pluck('title', 'id')->all())
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled') && $get('coupon_reward_scope') === Coupon::SCOPE_PRODUCT),
                                        ...collect(MoneyInput::convertedCents(
                                            TextInput::make('coupon_reward_minimum_order_cents')->label('最低订单金额')->default(0)->minValue(0),
                                            controlName: 'coupon_reward_minimum_order_cents'
                                        ))
                                            ->map(fn ($component) => $component->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')))
                                            ->all(),
                                        TextInput::make('coupon_reward_quantity')
                                            ->label('发放张数')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')),
                                        TextInput::make('coupon_reward_usage_limit')
                                            ->label('每张总可用次数')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled')),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $seenIds = [];

        foreach (($state['recharge_options'] ?? []) as $index => $optionData) {
            $id = (int) ($optionData['id'] ?? 0);
            $payload = [
                'name' => $optionData['name'] ?? null,
                'currency_code' => $optionData['currency_code'] ?: CurrencyUnit::baseCurrency(),
                'currency_unit' => $optionData['currency_unit'] ?: CurrencyUnit::baseUnit(),
                'amount_cents' => max(0, (int) ($optionData['amount_cents'] ?? 0)),
                'discount_percent' => filled($optionData['discount_percent'] ?? null) ? max(1, min(100, (int) $optionData['discount_percent'])) : null,
                'bonus_cents' => max(0, (int) ($optionData['bonus_cents'] ?? 0)),
                'is_active' => (bool) ($optionData['is_active'] ?? false),
                'sort_order' => (int) ($optionData['sort_order'] ?? $index),
                'coupon_reward_enabled' => (bool) ($optionData['coupon_reward_enabled'] ?? false),
                'coupon_reward_currency_code' => $optionData['coupon_reward_currency_code'] ?: CurrencyUnit::baseCurrency(),
                'coupon_reward_currency_unit' => $optionData['coupon_reward_currency_unit'] ?: CurrencyUnit::baseUnit(),
                'coupon_reward_type' => ($optionData['coupon_reward_type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED,
                'coupon_reward_value' => max(0, (int) ($optionData['coupon_reward_value'] ?? 0)),
                'coupon_reward_valid_days' => filled($optionData['coupon_reward_valid_days'] ?? null) ? max(1, (int) $optionData['coupon_reward_valid_days']) : null,
                'coupon_reward_scope' => ($optionData['coupon_reward_scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL,
                'coupon_reward_product_ids' => array_values(array_filter(array_map('intval', $optionData['coupon_reward_product_ids'] ?? []))),
                'coupon_reward_minimum_order_cents' => max(0, (int) ($optionData['coupon_reward_minimum_order_cents'] ?? 0)),
                'coupon_reward_quantity' => max(1, (int) ($optionData['coupon_reward_quantity'] ?? 1)),
                'coupon_reward_usage_limit' => max(1, (int) ($optionData['coupon_reward_usage_limit'] ?? 1)),
            ];

            $option = $id > 0
                ? WalletRechargeOption::query()->find($id)
                : null;

            if ($option) {
                $option->update($payload);
            } else {
                $option = WalletRechargeOption::query()->create($payload);
            }

            $seenIds[] = $option->id;
        }

        WalletRechargeOption::query()
            ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
            ->when($seenIds === [], fn ($query) => $query)
            ->delete();

        $this->mount();

        Notification::make()->title('钱包设置已保存')->success()->send();
    }
}
