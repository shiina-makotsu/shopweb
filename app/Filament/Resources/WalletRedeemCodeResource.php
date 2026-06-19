<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\WalletRedeemCodeResource\Pages\CreateWalletRedeemCode;
use App\Filament\Resources\WalletRedeemCodeResource\Pages\EditWalletRedeemCode;
use App\Filament\Resources\WalletRedeemCodeResource\Pages\ListWalletRedeemCodes;
use App\Models\WalletRedeemCode;
use App\Support\Money;
use App\Support\MoneyInput;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WalletRedeemCodeResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = WalletRedeemCode::class;
    protected static string $permissionArea = 'payments';
    protected static ?string $navigationLabel = '钱包兑换码';
    protected static ?string $modelLabel = '钱包兑换码';
    protected static ?string $pluralModelLabel = '钱包兑换码';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('兑换码信息')
                ->schema([
                    TextInput::make('code')
                        ->label('兑换码')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                    TextInput::make('name')->label('名称')->maxLength(255),
                    MoneyInput::cents(TextInput::make('amount_cents')->label('兑换金额')->required()),
                    TextInput::make('usage_limit')->label('可兑换次数')->numeric()->minValue(1)->default(1)->required(),
                    TextInput::make('redeemed_count')->label('已兑换次数')->numeric()->disabled()->dehydrated(false),
                    Toggle::make('is_active')->label('启用')->default(true),
                    DateTimePicker::make('starts_at')->label('开始时间')->native(false),
                    DateTimePicker::make('ends_at')->label('结束时间')->native(false),
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
                TextColumn::make('code')->label('兑换码')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->placeholder('-'),
                TextColumn::make('amount_cents')->label('金额')->formatStateUsing(fn ($state): string => Money::format((int) $state))->sortable(),
                TextColumn::make('redeemed_count')->label('已兑换')->sortable(),
                TextColumn::make('usage_limit')->label('上限')->sortable(),
                TextColumn::make('is_active')->label('状态')->formatStateUsing(fn (bool $state): string => $state ? '启用' : '停用')->badge(),
                TextColumn::make('ends_at')->label('结束时间')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletRedeemCodes::route('/'),
            'create' => CreateWalletRedeemCode::route('/create'),
            'edit' => EditWalletRedeemCode::route('/{record}/edit'),
        ];
    }
}
