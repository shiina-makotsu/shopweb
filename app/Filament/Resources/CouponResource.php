<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use App\Support\MoneyInput;
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
            Select::make('product_id')
                ->label('指定商品')
                ->relationship('product', 'title')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->required(fn (Get $get): bool => $get('scope') === Coupon::SCOPE_PRODUCT)
                ->dehydrateStateUsing(fn ($state, Get $get) => $get('scope') === Coupon::SCOPE_PRODUCT ? $state : null),
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
                TextColumn::make('product.title')->label('指定商品')->toggleable(),
                TextColumn::make('value')->label('值')->formatStateUsing(fn ($state, Coupon $record): string => $record->type === Coupon::TYPE_FIXED ? \App\Support\Money::format((int) $state) : ((int) $state).'%'),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('redemptions_count')->counts('redemptions')->label('使用记录'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
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
