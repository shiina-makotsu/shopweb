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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            TextInput::make('code')->label('代码')->required()->maxLength(100)->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state))),
            TextInput::make('name')->label('名称')->required()->maxLength(255),
            Select::make('type')->label('类型')->required()->options([
                Coupon::TYPE_FIXED => '固定金额',
                Coupon::TYPE_PERCENT => '百分比',
            ])->default(Coupon::TYPE_FIXED)->live(),
            Select::make('scope')->label('适用范围')->required()->options(Coupon::scopeOptions())->default(Coupon::SCOPE_GLOBAL)->live(),
            Select::make('products')
                ->label('可用商品')
                ->relationship('products', 'title')
                ->multiple()
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->required(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->helperText('单商品优惠码可以选择多个适用商品，结算时这些商品都可使用。'),
            TextInput::make('value')
                ->label(fn (Get $get): string => $get('type') === Coupon::TYPE_PERCENT ? '折扣百分比' : '优惠金额（元）')
                ->numeric()
                ->required()
                ->minValue(0)
                ->helperText('固定金额按元输入；百分比填写 1-100。')
                ->formatStateUsing(fn ($state, $record): ?string => $record?->type === Coupon::TYPE_FIXED ? MoneyInput::fromCents($state) : ($state === null ? null : (string) $state))
                ->dehydrateStateUsing(fn ($state, Get $get): int => $get('type') === Coupon::TYPE_FIXED ? MoneyInput::toCents($state) : max(0, min(100, (int) $state))),
            MoneyInput::cents(TextInput::make('minimum_order_cents')->label('最低订单金额（元）')->default(0)),
            TextInput::make('usage_limit')->label('总次数')->numeric(),
            DateTimePicker::make('starts_at')->label('开始时间'),
            DateTimePicker::make('ends_at')->label('结束时间'),
            Toggle::make('is_active')->label('启用')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('代码')->searchable(),
                TextColumn::make('name')->label('名称'),
                TextColumn::make('type')->label('类型'),
                TextColumn::make('scope')->label('范围')->formatStateUsing(fn (?string $state): string => Coupon::scopeOptions()[$state ?? Coupon::SCOPE_GLOBAL] ?? (string) $state),
                TextColumn::make('products.title')->label('可用商品')->listWithLineBreaks()->limitList(3)->toggleable(),
                TextColumn::make('value')->label('值')->formatStateUsing(fn ($state, Coupon $record): string => $record->type === Coupon::TYPE_FIXED ? \App\Support\Money::format((int) $state) : ((int) $state).'%'),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('redemptions_count')->counts('redemptions')->label('使用记录'),
            ])
            ->recordActions([
                Action::make('issueToUser')
                    ->label('发放给用户')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->form([
                        Select::make('user_id')
                            ->label('用户')
                            ->options(fn (): array => User::query()->where('role', 'customer')->latest()->limit(100)->pluck('email', 'id')->all())
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

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
