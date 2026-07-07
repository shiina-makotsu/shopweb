<?php

namespace App\Filament\Pages;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\WalletRechargeOption;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Filament\Support\AdminPageTabs;
use App\Filament\Support\CardPreviewTemplate;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\Money;
use App\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;

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
                    '_stored' => true,
                    '_placeholder' => false,
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
                    'coupon_reward_rules' => $option->couponRewardRules(),
                ])
                ->push(static::emptyRechargeOptionState())
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
                        Placeholder::make('_recharge_page_preview')
                            ->label('用户端充值页面整体预览')
                            ->content(fn (Get $get): HtmlString => static::rechargeOptionsPagePreviewHtml($get('recharge_options') ?: []))
                            ->columnSpanFull(),
                        Repeater::make('recharge_options')
                            ->label('预览卡片设置')
                            ->addable(false)
                            ->collapsible()
                            ->extraAttributes([
                                'data-wallet-recharge-settings' => 'true',
                                'style' => 'display:none;',
                            ])
                            ->itemLabel(fn (array $state): string => static::rechargeOptionSettingLabel($state))
                            ->reorderable()
                            ->schema([
                                Hidden::make('id'),
                                Hidden::make('_stored')->default(false),
                                Hidden::make('_placeholder')->default(false),
                                TextInput::make('name')->label('名称')->maxLength(255),
                                ...MoneyInput::conversionControls('amount_cents', 'currency_code', 'currency_unit', dehydrated: true),
                                TextInput::make('amount_cents')
                                    ->label('充值面额')
                                    ->numeric()
                                    ->required(fn (Get $get): bool => static::rechargeOptionHasInputFromGet($get))
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
                                        Repeater::make('coupon_reward_rules')
                                            ->label('赠券规则')
                                            ->addActionLabel('添加一种赠券')
                                            ->reorderable()
                                            ->defaultItems(0)
                                            ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled'))
                                            ->schema([
                                                TextInput::make('name')->label('规则备注')->maxLength(80)->helperText('仅用于后台区分，可不填。'),
                                                Select::make('type')
                                                    ->label('赠券类型')
                                                    ->options([
                                                        Coupon::TYPE_FIXED => '固定金额',
                                                        Coupon::TYPE_PERCENT => '折扣百分比',
                                                    ])
                                                    ->default(Coupon::TYPE_FIXED)
                                                    ->live(),
                                                ...collect(MoneyInput::conversionControls('value', 'currency_code', 'currency_unit', dehydrated: true))
                                                    ->map(fn ($component) => $component->visible(fn (Get $get): bool => $get('type') !== Coupon::TYPE_PERCENT))
                                                    ->all(),
                                                TextInput::make('value')
                                                    ->label(fn (Get $get): string => $get('type') === Coupon::TYPE_PERCENT ? '折扣百分比' : '优惠金额')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->helperText('固定金额按所选币种单位录入；折扣百分比填写 1-100。')
                                                    ->formatStateUsing(function ($state, Get $get): ?string {
                                                        if ($state === null || $state === '') {
                                                            return null;
                                                        }

                                                        return $get('type') === Coupon::TYPE_PERCENT
                                                            ? (string) $state
                                                            : CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1);
                                                    })
                                                    ->dehydrateStateUsing(function ($state, Get $get): int {
                                                        if ($get('type') === Coupon::TYPE_PERCENT) {
                                                            return max(0, min(100, (int) $state));
                                                        }

                                                        $currency = CurrencyUnit::normalizeCurrency($get('currency_code') ?: CurrencyUnit::baseCurrency());

                                                        return CurrencyUnit::toSettlementCents(
                                                            $state,
                                                            $currency,
                                                            $get('currency_unit') ?: CurrencyUnit::defaultUnit($currency),
                                                            CurrencyUnit::exchangeRateFor($currency),
                                                        );
                                                    }),
                                                TextInput::make('valid_days')
                                                    ->label('有效天数')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->helperText('不填则永久有效。'),
                                                Select::make('scope')
                                                    ->label('适用范围')
                                                    ->options(Coupon::scopeOptions())
                                                    ->default(Coupon::SCOPE_GLOBAL)
                                                    ->live(),
                                                Select::make('product_ids')
                                                    ->label('指定商品')
                                                    ->options(fn (): array => Product::query()->orderBy('title')->limit(200)->pluck('title', 'id')->all())
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->visible(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT),
                                                TextInput::make('minimum_order_cents')
                                                    ->label('最低订单金额')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->helperText('使用本条规则的币种和单位。')
                                                    ->formatStateUsing(fn ($state): ?string => CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1))
                                                    ->dehydrateStateUsing(function ($state, Get $get): int {
                                                        $currency = CurrencyUnit::normalizeCurrency($get('currency_code') ?: CurrencyUnit::baseCurrency());

                                                        return CurrencyUnit::toSettlementCents(
                                                            $state,
                                                            $currency,
                                                            $get('currency_unit') ?: CurrencyUnit::defaultUnit($currency),
                                                            CurrencyUnit::exchangeRateFor($currency),
                                                        );
                                                    }),
                                                TextInput::make('quantity')
                                                    ->label('本规则发放张数')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1),
                                                TextInput::make('usage_limit')
                                                    ->label('每张总可用次数')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1),
                                                Toggle::make('is_stackable')
                                                    ->label('允许该赠券同单叠加')
                                                    ->helperText('默认关闭；关闭后，该规则发放的每一张优惠券都不能和任意第二张优惠券在同一笔订单中同时使用，包括同规则发放的其它赠券。')
                                                    ->default(false),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
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
            if (static::isBlankRechargeOptionData($optionData)) {
                continue;
            }

            $id = (int) ($optionData['id'] ?? 0);
            $couponRewardRules = $this->normalizeCouponRewardRules($optionData['coupon_reward_rules'] ?? []);
            $firstCouponRewardRule = $couponRewardRules[0] ?? [];
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
                'coupon_reward_currency_code' => $firstCouponRewardRule['currency_code'] ?? CurrencyUnit::baseCurrency(),
                'coupon_reward_currency_unit' => $firstCouponRewardRule['currency_unit'] ?? CurrencyUnit::baseUnit(),
                'coupon_reward_type' => $firstCouponRewardRule['type'] ?? Coupon::TYPE_FIXED,
                'coupon_reward_value' => $firstCouponRewardRule['value'] ?? 0,
                'coupon_reward_valid_days' => $firstCouponRewardRule['valid_days'] ?? null,
                'coupon_reward_scope' => $firstCouponRewardRule['scope'] ?? Coupon::SCOPE_GLOBAL,
                'coupon_reward_product_ids' => $firstCouponRewardRule['product_ids'] ?? [],
                'coupon_reward_minimum_order_cents' => $firstCouponRewardRule['minimum_order_cents'] ?? 0,
                'coupon_reward_quantity' => $firstCouponRewardRule['quantity'] ?? 1,
                'coupon_reward_usage_limit' => $firstCouponRewardRule['usage_limit'] ?? 1,
                'coupon_reward_rules' => $couponRewardRules,
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

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCouponRewardRules(array $rules): array
    {
        return collect($rules)
            ->map(function (array $rule): array {
                $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED;
                $scope = ($rule['scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL;

                return [
                    'name' => trim((string) ($rule['name'] ?? '')),
                    'currency_code' => CurrencyUnit::normalizeCurrency($rule['currency_code'] ?? CurrencyUnit::baseCurrency()),
                    'currency_unit' => $rule['currency_unit'] ?: CurrencyUnit::defaultUnit($rule['currency_code'] ?? CurrencyUnit::baseCurrency()),
                    'type' => $type,
                    'value' => $type === Coupon::TYPE_PERCENT
                        ? max(0, min(100, (int) ($rule['value'] ?? 0)))
                        : max(0, (int) ($rule['value'] ?? 0)),
                    'valid_days' => filled($rule['valid_days'] ?? null) ? max(1, (int) $rule['valid_days']) : null,
                    'scope' => $scope,
                    'product_ids' => array_values(array_filter(array_map('intval', $rule['product_ids'] ?? []))),
                    'minimum_order_cents' => max(0, (int) ($rule['minimum_order_cents'] ?? 0)),
                    'quantity' => max(1, (int) ($rule['quantity'] ?? 1)),
                    'usage_limit' => max(1, (int) ($rule['usage_limit'] ?? 1)),
                    'is_stackable' => (bool) ($rule['is_stackable'] ?? false),
                ];
            })
            ->filter(fn (array $rule): bool => (int) $rule['value'] > 0 && (int) $rule['quantity'] > 0)
            ->values()
            ->all();
    }

    private static function emptyRechargeOptionState(): array
    {
        return [
            'id' => null,
            '_stored' => false,
            '_placeholder' => true,
            'name' => null,
            'currency_code' => CurrencyUnit::baseCurrency(),
            'currency_unit' => CurrencyUnit::baseUnit(),
            'amount_cents' => null,
            'discount_percent' => null,
            'bonus_cents' => null,
            'is_active' => true,
            'sort_order' => null,
            'coupon_reward_enabled' => false,
            'coupon_reward_rules' => [],
        ];
    }

    private static function rechargeOptionStateFromGet(Get $get): array
    {
        return [
            'id' => $get('id'),
            '_stored' => $get('_stored'),
            '_placeholder' => $get('_placeholder'),
            'name' => $get('name'),
            'currency_code' => $get('currency_code'),
            'currency_unit' => $get('currency_unit'),
            'amount_cents' => $get('amount_cents'),
            'discount_percent' => $get('discount_percent'),
            'bonus_cents' => $get('bonus_cents'),
            'is_active' => $get('is_active'),
            'coupon_reward_enabled' => $get('coupon_reward_enabled'),
            'coupon_reward_rules' => $get('coupon_reward_rules') ?: [],
        ];
    }

    private static function rechargeOptionHasInputFromGet(Get $get): bool
    {
        return ! static::isBlankRechargeOptionData(static::rechargeOptionStateFromGet($get));
    }

    private static function rechargeOptionSettingLabel(array $state): string
    {
        if (static::isBlankRechargeOptionData($state)) {
            return '新增充值选项设置（后台占位）';
        }

        return trim((string) ($state['name'] ?? '')) ?: '充值选项设置';
    }

    private static function isBlankRechargeOptionData(array $data): bool
    {
        if ((int) ($data['id'] ?? 0) > 0) {
            return false;
        }

        return ! filled($data['name'] ?? null)
            && ! filled($data['amount_cents'] ?? null)
            && ! filled($data['discount_percent'] ?? null)
            && (int) ($data['bonus_cents'] ?? 0) <= 0
            && ! (bool) ($data['coupon_reward_enabled'] ?? false)
            && ($data['coupon_reward_rules'] ?? []) === [];
    }

    private static function rechargeOptionsPagePreviewHtml(array $options): HtmlString
    {
        $cards = collect($options)
            ->values()
            ->map(fn (array $state, int $index): string => static::rechargeOptionPreviewCardHtml($state, $index))
            ->filter()
            ->implode('');

        if ($cards === '') {
            $cards = static::rechargeOptionPreviewCardHtml(static::emptyRechargeOptionState(), 0);
        }

        $initialOrder = collect($options)->values()->keys()->implode(',');

        return CardPreviewTemplate::render([
            'key' => 'wallet-recharge',
            'title' => '充值钱包',
            'description' => '点击下方卡片后，在预览下方展开对应设置；后台占位卡不会显示给用户。',
            'badge' => '用户端页面预览',
            'cards' => $cards,
            'settingsSelector' => '[data-wallet-recharge-settings]',
            'legacyRootAttributes' => 'data-wallet-recharge-page-preview data-wallet-recharge-original-order="'.$initialOrder.'"',
            'legacyGridAttributes' => 'data-wallet-recharge-preview-grid',
            'legacySaveAttributes' => 'data-wallet-recharge-save-order',
            'enableSorting' => true,
            'originalOrder' => $initialOrder,
            'sortFieldLabel' => '排序',
            'saveLabel' => '保存排序',
        ]);

    }

    private static function rechargeOptionPreviewCardHtml(array $state, int $index): string
    {
        $isBlank = static::isBlankRechargeOptionData($state);
        $amountCents = $isBlank ? 0 : static::previewAmountCents($state);
        $discount = filled($state['discount_percent'] ?? null)
            ? max(1, min(100, (int) $state['discount_percent']))
            : 100;
        $payableCents = (int) round($amountCents * $discount / 100);
        $bonusCents = static::previewBonusCents($state);
        $creditCents = $amountCents + $bonusCents;
        $configuredTitle = trim((string) ($state['name'] ?? ''));
        $id = (int) ($state['id'] ?? 0);
        $title = $isBlank
            ? '新增充值选项'
            : ($configuredTitle !== '' ? $configuredTitle : ($amountCents > 0 ? Money::format($amountCents).' 充值' : ($id > 0 ? '充值选项 #'.$id : '充值选项')));
        $title = e($title);
        $badge = $isBlank ? '后台占位，不会显示给用户' : ((bool) ($state['is_active'] ?? true) ? '用户端显示' : '已停用');
        $badgeColor = $isBlank ? '#64748b' : ((bool) ($state['is_active'] ?? true) ? '#047857' : '#b91c1c');
        $payable = $isBlank ? '待设置' : e(Money::format($payableCents));
        $credit = $isBlank ? '待设置' : e(Money::format($creditCents));
        $discountText = $discount < 100 ? '<span>按 '.$discount.'% 付款</span>' : '<span>无折扣</span>';
        $bonusText = $bonusCents > 0 ? '<span>赠送 '.e(Money::format($bonusCents)).'</span>' : '<span>无赠送余额</span>';
        $couponParts = static::previewCouponRewardParts($state);
        $couponText = $couponParts === []
            ? '<span>无赠券</span>'
            : '<span>赠券 '.e(implode('+', $couponParts)).'</span>';

        $footer = '<span style="display:flex;flex-wrap:wrap;gap:6px;color:#475569;font-size:12px;">'.$discountText.$bonusText.$couponText.'</span>';

        return CardPreviewTemplate::card([
            'index' => $index,
            'title' => html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8'),
            'subtitle' => $badge,
            'metrics' => [
                ['label' => '用户实付', 'value' => html_entity_decode((string) $payable, ENT_QUOTES, 'UTF-8'), 'class' => 'shop-admin-preview-price'],
                ['label' => '钱包到账', 'value' => html_entity_decode((string) $credit, ENT_QUOTES, 'UTF-8'), 'class' => 'shop-admin-preview-price'],
            ],
            'footer' => $footer,
            'legacyAttributes' => 'data-wallet-recharge-preview-card="'.$index.'" data-wallet-recharge-placeholder="'.($isBlank ? '1' : '0').'"',
            'isPlaceholder' => $isBlank,
            'draggable' => ! $isBlank,
        ]);

    }

    private static function previewAmountCents(array $state): int
    {
        $value = $state['amount_cents'] ?? 0;

        if (! filled($value)) {
            return 0;
        }

        $currency = CurrencyUnit::normalizeCurrency($state['currency_code'] ?? CurrencyUnit::baseCurrency());

        return CurrencyUnit::toSettlementCents(
            $value,
            $currency,
            $state['currency_unit'] ?: CurrencyUnit::defaultUnit($currency),
            CurrencyUnit::exchangeRateFor($currency),
        );
    }

    private static function previewBonusCents(array $state): int
    {
        $value = $state['bonus_cents'] ?? 0;

        if (! filled($value)) {
            return 0;
        }

        $currency = CurrencyUnit::normalizeCurrency($state['currency_code'] ?? CurrencyUnit::baseCurrency());

        return CurrencyUnit::toSettlementCents(
            $value,
            $currency,
            $state['currency_unit'] ?: CurrencyUnit::defaultUnit($currency),
            CurrencyUnit::exchangeRateFor($currency),
        );
    }

    /**
     * @return array<int, string>
     */
    private static function previewCouponRewardParts(array $state): array
    {
        if (! (bool) ($state['coupon_reward_enabled'] ?? false)) {
            return [];
        }

        return collect($state['coupon_reward_rules'] ?? [])
            ->map(function (array $rule): ?string {
                $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT
                    ? Coupon::TYPE_PERCENT
                    : Coupon::TYPE_FIXED;
                $value = $rule['value'] ?? null;

                if (! filled($value)) {
                    return null;
                }

                $label = $type === Coupon::TYPE_PERCENT
                    ? max(0, min(100, (int) $value)).'%折扣券'
                    : Money::format(static::previewCouponRewardValueCents($rule));
                $quantity = max(1, (int) ($rule['quantity'] ?? 1));

                return $quantity > 1 ? $label.'*'.$quantity : $label;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function previewCouponRewardValueCents(array $rule): int
    {
        $currency = CurrencyUnit::normalizeCurrency($rule['currency_code'] ?? CurrencyUnit::baseCurrency());

        return CurrencyUnit::toSettlementCents(
            $rule['value'] ?? 0,
            $currency,
            $rule['currency_unit'] ?: CurrencyUnit::defaultUnit($currency),
            CurrencyUnit::exchangeRateFor($currency),
        );
    }
}
