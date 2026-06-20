<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\CouponService;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\Money;
use App\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\HtmlString;

class CouponResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Coupon::class;
    protected static string $permissionArea = 'coupons';
    protected static ?string $navigationLabel = '优惠码';
    protected static ?string $modelLabel = '优惠码';
    protected static ?string $pluralModelLabel = '优惠码';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('代码')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state))),
            TextInput::make('name')->label('名称')->required()->maxLength(255),
            Select::make('type')
                ->label('类型')
                ->required()
                ->options([
                    Coupon::TYPE_FIXED => '固定金额',
                    Coupon::TYPE_PERCENT => '百分比',
                ])
                ->default(Coupon::TYPE_FIXED)
                ->live(),
            Select::make('scope')
                ->label('适用范围')
                ->required()
                ->options(Coupon::scopeOptions())
                ->default(Coupon::SCOPE_GLOBAL)
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    if ($state !== Coupon::SCOPE_PRODUCT) {
                        $set('products', []);
                        $set('product_id', null);
                    }
                }),
            Select::make('products')
                ->label('可用商品')
                ->relationship('products', 'title')
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->required(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->helperText('单商品优惠码可以选择多个适用商品，结算时这些商品都可以使用。'),
            Section::make('折扣设置')
                ->schema([
                    ...collect(MoneyInput::conversionControls('value'))
                        ->map(fn ($component) => $component->visible(fn (Get $get): bool => $get('type') === Coupon::TYPE_FIXED))
                        ->all(),
                    TextInput::make('value')
                        ->label(fn (Get $get): string => $get('type') === Coupon::TYPE_PERCENT ? '折扣百分比' : '优惠金额')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('固定金额按所选货币单位录入并折算为网站基准货币；百分比填写 1-100。')
                        ->formatStateUsing(fn ($state, $record): ?string => $record?->type === Coupon::TYPE_FIXED ? MoneyInput::fromCents($state) : ($state === null ? null : (string) $state))
                        ->dehydrateStateUsing(function ($state, Get $get): int {
                            if ($get('type') !== Coupon::TYPE_FIXED) {
                                return max(0, min(100, (int) $state));
                            }

                            $currency = $get('value_currency_code') ?: CurrencyUnit::baseCurrency();

                            return CurrencyUnit::toSettlementCents(
                                $state,
                                $currency,
                                $get('value_currency_unit') ?: CurrencyUnit::defaultUnit($currency),
                                CurrencyUnit::exchangeRateFor($currency),
                            );
                        }),
                ])
                ->columns(3)
                ->columnSpanFull(),
            MoneyInput::currencyAmountSection(
                TextInput::make('minimum_order_cents')->label('最低订单金额')->default(0),
                label: '最低订单金额'
            ),
            TextInput::make('usage_limit')->label('总次数')->numeric(),
            DateTimePicker::make('starts_at')->label('开始时间'),
            DateTimePicker::make('ends_at')->label('结束时间'),
            Toggle::make('is_active')->label('启用')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['products', 'userCoupons.user'])->withCount('userCoupons'))
            ->columns([
                TextColumn::make('code')->label('代码')->searchable(),
                TextColumn::make('name')->label('名称'),
                TextColumn::make('type')->label('类型'),
                TextColumn::make('scope')
                    ->label('范围')
                    ->formatStateUsing(fn (?string $state): string => Coupon::scopeOptions()[$state ?? Coupon::SCOPE_GLOBAL] ?? (string) $state),
                TextColumn::make('applicable_products')
                    ->label('可用商品')
                    ->state(fn (Coupon $record): array => $record->scope === Coupon::SCOPE_PRODUCT
                        ? $record->products->pluck('title')->all()
                        : [])
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('value')
                    ->label('值')
                    ->formatStateUsing(fn ($state, Coupon $record): string => $record->type === Coupon::TYPE_FIXED ? Money::format((int) $state) : ((int) $state).'%'),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('user_coupons_count')
                    ->label('持有用户')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ((int) $state).' 人')
                    ->sortable(),
                TextColumn::make('coupon_holders')
                    ->label('持有人明细')
                    ->state(fn (Coupon $record): array => static::couponHolderLabels($record, 5))
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('redemptions_count')->counts('redemptions')->label('使用记录'),
            ])
            ->recordActions([
                Action::make('viewHolders')
                    ->label('查看持有人')
                    ->icon(Heroicon::OutlinedUsers)
                    ->modalHeading(fn (Coupon $record): string => '优惠码持有人：'.$record->code)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalWidth('5xl')
                    ->modalContent(fn (Coupon $record): HtmlString => static::couponHoldersHtml($record)),
                Action::make('issueToUser')
                    ->label('发放给用户')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => AdminAccess::canAction('coupons.issue'))
                    ->form([
                        Select::make('user_id')
                            ->label('用户')
                            ->options(fn (): array => static::customerOptions())
                            ->searchable()
                            ->required(),
                        TextInput::make('note')->label('备注')->maxLength(255),
                    ])
                    ->action(function (Coupon $record, array $data): void {
                        $user = User::query()->findOrFail($data['user_id']);

                        app(CouponService::class)->issueToUser(
                            $record,
                            $user,
                            UserCoupon::SOURCE_ADMIN,
                            auth()->user(),
                            null,
                            $data['note'] ?? null,
                        );
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function issueCouponHeaderAction(): Action
    {
        return Action::make('issueCoupon')
            ->label('发放优惠码')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => AdminAccess::canAction('coupons.issue'))
            ->form([
                Select::make('coupon_id')
                    ->label('优惠码')
                    ->options(fn (): array => static::couponOptions())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('user_id')
                    ->label('用户')
                    ->options(fn (): array => static::customerOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('note')->label('备注')->maxLength(255),
            ])
            ->action(function (array $data): void {
                $coupon = Coupon::query()->findOrFail($data['coupon_id']);
                $user = User::query()->findOrFail($data['user_id']);

                app(CouponService::class)->issueToUser(
                    $coupon,
                    $user,
                    UserCoupon::SOURCE_ADMIN,
                    auth()->user(),
                    null,
                    $data['note'] ?? null,
                );
            });
    }

    /**
     * @return array<int, string>
     */
    private static function couponHolderLabels(Coupon $coupon, int $limit = 5): array
    {
        $coupon->loadMissing('userCoupons.user');

        return $coupon->userCoupons
            ->take($limit)
            ->map(function (UserCoupon $userCoupon): string {
                $user = $userCoupon->user;

                if (! $user) {
                    return '已删除用户 #'.$userCoupon->user_id;
                }

                return $user->displayName().' / '.$user->public_id.' / '.$user->email;
            })
            ->values()
            ->all();
    }

    private static function couponHoldersHtml(Coupon $coupon): HtmlString
    {
        $holders = $coupon->userCoupons()
            ->with(['user', 'issuer'])
            ->latest('claimed_at')
            ->latest('id')
            ->get();

        if ($holders->isEmpty()) {
            return new HtmlString('<p style="margin:0;color:#64748b;">暂无用户持有该优惠码。</p>');
        }

        $rows = $holders->map(function (UserCoupon $userCoupon): string {
            $user = $userCoupon->user;
            $issuer = $userCoupon->issuer;
            $userLabel = $user
                ? e($user->displayName()).'<br><span style="color:#64748b;">'.e($user->public_id).' / '.e($user->email).'</span>'
                : '<span style="color:#dc2626;">已删除用户 #'.e((string) $userCoupon->user_id).'</span>';
            $source = e(static::couponSourceLabel($userCoupon->source));
            $issuerLabel = $issuer ? e($issuer->displayName()) : '-';
            $claimedAt = $userCoupon->claimed_at?->format('Y-m-d H:i') ?? '-';
            $note = filled($userCoupon->note) ? e($userCoupon->note) : '-';

            return <<<HTML
                <tr>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;vertical-align:top;">{$userLabel}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;vertical-align:top;">{$source}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;vertical-align:top;">{$issuerLabel}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;vertical-align:top;white-space:nowrap;">{$claimedAt}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;vertical-align:top;">{$note}</td>
                </tr>
            HTML;
        })->implode('');

        return new HtmlString(<<<HTML
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.5;">
                    <thead>
                        <tr style="background:#f8fafc;color:#334155;">
                            <th style="padding:10px 12px;text-align:left;">用户</th>
                            <th style="padding:10px 12px;text-align:left;">来源</th>
                            <th style="padding:10px 12px;text-align:left;">发放人</th>
                            <th style="padding:10px 12px;text-align:left;">获得时间</th>
                            <th style="padding:10px 12px;text-align:left;">备注</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        HTML);
    }

    private static function couponSourceLabel(?string $source): string
    {
        return match ($source) {
            UserCoupon::SOURCE_CLAIMED => '用户领取',
            UserCoupon::SOURCE_ADMIN => '后台发放',
            UserCoupon::SOURCE_AFTER_SALES => '售后补偿',
            default => $source ?: '-',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function couponOptions(): array
    {
        return Coupon::query()
            ->latest()
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Coupon $coupon): array => [
                $coupon->id => $coupon->code.' - '.$coupon->name,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function customerOptions(): array
    {
        return User::query()
            ->where('role', 'customer')
            ->latest()
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->displayName().' / '.$user->public_id.' / '.$user->email,
            ])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
