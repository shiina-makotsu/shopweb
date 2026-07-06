<?php

namespace App\Filament\Pages;

use App\Models\WalletRechargeOption;
use App\Filament\Resources\WalletRedeemCodeResource;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
        return [
            Action::make('redeemCodes')
                ->label('兑换码管理')
                ->url(WalletRedeemCodeResource::getUrl('index')),
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
