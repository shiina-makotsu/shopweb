<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\CreateReferralRewardRule;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\EditReferralRewardRule;
use App\Filament\Resources\ReferralRewardRuleResource\Pages\ListReferralRewardRules;
use App\Models\ReferralRewardRule;
use App\Support\CurrencyUnit;
use App\Support\Money;
use App\Support\MoneyInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
    protected static ?string $navigationLabel = '邀请奖励设置';
    protected static ?string $modelLabel = '邀请奖励规则';
    protected static ?string $pluralModelLabel = '邀请奖励规则';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;
    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('邀请奖励')
                ->description('用户通过邀请码注册成功后，系统会按启用的规则给邀请人发放优惠码、钱包余额，或同时发放两项。')
                ->schema([
                    TextInput::make('name')->label('规则名称')->required()->maxLength(255),
                    Toggle::make('is_active')->label('启用')->default(true),
                    Select::make('coupon_id')
                        ->label('自动发放优惠码')
                        ->options(fn (): array => CouponResource::couponOptions())
                        ->searchable()
                        ->preload()
                        ->nullable(),
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
                TextColumn::make('coupon.code')->label('优惠码')->placeholder('-'),
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
}
