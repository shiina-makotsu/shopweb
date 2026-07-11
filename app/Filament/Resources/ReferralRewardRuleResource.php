<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\CreateReferralRewardRule;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\EditReferralRewardRule;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\ListReferralRewardRules;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ReferralRewardRule;
use App\Support\CurrencyUnit;
use App\Support\Money;
use App\Support\MoneyInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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

class ReferralRewardRuleResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ReferralRewardRule::class;
    protected static string $permissionArea = 'coupons';
    protected static ?string $navigationLabel = '奖励规则';
    protected static ?string $modelLabel = '事件奖励规则';
    protected static ?string $pluralModelLabel = '事件奖励规则';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;
    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('添加事件奖励规则')
                ->description('选择一个或多个触发事件后，用户完成对应行为即可获得同一组自动生成优惠券或钱包余额奖励。')
                ->schema([
                    TextInput::make('name')->label('规则名称')->required()->maxLength(255),
                    Toggle::make('is_active')->label('启用')->default(true),
                    Select::make('trigger_events')
                        ->label('触发事件')
                        ->multiple()
                        ->options(ReferralRewardRule::eventOptions())
                        ->default([ReferralRewardRule::EVENT_REFERRAL_REGISTERED])
                        ->required()
                        ->live(),
                    Select::make('product_ids')
                        ->label('指定商品')
                        ->multiple()
                        ->options(fn (): array => Product::query()
                            ->orderBy('sort_order')
                            ->orderByDesc('id')
                            ->limit(200)
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->helperText('仅“购买指定商品”事件使用；留空表示购买任意商品都可触发。')
                        ->visible(fn (Get $get): bool => in_array(ReferralRewardRule::EVENT_ORDER_PAID_PRODUCT, $get('trigger_events') ?? [], true)),
                    Select::make('coupon_id')
                        ->label('兼容旧优惠券')
                        ->options(fn (): array => CouponResource::couponOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->hidden(),
                    Section::make('自动生成并发放优惠券')
                        ->description('和钱包充值赠券一样，触发事件后系统会自动生成普通优惠码并发放给用户；生成后的优惠码仍在“优惠码”中查看。')
                        ->schema([
                            Toggle::make('coupon_reward_enabled')
                                ->label('启用事件赠券')
                                ->default(false)
                                ->live(),
                            Repeater::make('coupon_reward_rules')
                                ->label('赠券规则')
                                ->addActionLabel('添加一种赠券')
                                ->reorderable()
                                ->defaultItems(0)
                                ->visible(fn (Get $get): bool => (bool) $get('coupon_reward_enabled'))
                                ->schema(static::couponRewardRuleSchema())
                                ->columns(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    ...MoneyInput::conversionControls('wallet_amount_cents'),
                    TextInput::make('wallet_amount_cents')
                        ->label('自动发放钱包余额')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('填 0 表示不发放钱包余额。')
                        ->formatStateUsing(fn ($state): ?string => CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1))
                        ->dehydrateStateUsing(fn ($state, callable $get): int => CurrencyUnit::toSettlementCents(
                            $state,
                            CurrencyUnit::normalizeCurrency($get('wallet_amount_cents_currency_code') ?: CurrencyUnit::baseCurrency()),
                            $get('wallet_amount_cents_currency_unit') ?: CurrencyUnit::defaultUnit($get('wallet_amount_cents_currency_code') ?: CurrencyUnit::baseCurrency()),
                            CurrencyUnit::exchangeRateFor($get('wallet_amount_cents_currency_code') ?: CurrencyUnit::baseCurrency()),
                        )),
                    TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                    Textarea::make('note')->label('备注')->rows(3)->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('规则名称')->searchable()->sortable(),
                TextColumn::make('event_labels')
                    ->label('触发事件')
                    ->badge()
                    ->separator(','),
                TextColumn::make('coupon_reward_rules')
                    ->label('赠券')
                    ->formatStateUsing(fn ($state, ReferralRewardRule $record): string => $record->couponRewardEnabled()
                        ? count($record->couponRewardRules()).' 种规则'
                        : ($record->coupon?->code ?: '-')),
                TextColumn::make('wallet_amount_cents')
                    ->label('钱包余额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) ($state ?? 0)))
                    ->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralRewardRules::route('/'),
            'create' => CreateReferralRewardRule::route('/create'),
            'edit' => EditReferralRewardRule::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function couponRewardRuleSchema(): array
    {
        return [
            TextInput::make('name')->label('规则备注')->maxLength(80),
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
            TextInput::make('valid_days')->label('有效天数')->numeric()->minValue(1),
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
            TextInput::make('quantity')->label('本规则发放张数')->numeric()->minValue(1)->default(1),
            TextInput::make('usage_limit')->label('每张总可用次数')->numeric()->minValue(1)->default(1),
            Toggle::make('is_stackable')->label('允许该赠券同单叠加')->default(false),
        ];
    }
}
