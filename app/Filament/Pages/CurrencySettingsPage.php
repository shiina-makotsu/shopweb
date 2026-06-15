<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\CurrencyRateService;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class CurrencySettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected string $view = 'filament.pages.currency-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = '财务';

    protected static ?string $navigationLabel = '货币';

    protected static ?string $slug = 'currency-settings';

    protected static ?int $navigationSort = 12;

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $converter_amount = '1';

    public string $converter_from = 'USD';

    public string $converter_from_unit = 'dollar';

    public string $converter_to = 'CNY';

    public string $converter_to_unit = 'yuan';

    public ?string $converter_result = null;

    public function mount(): void
    {
        $settings = $this->settings();
        $base = CurrencyUnit::baseCurrency();

        if (! $this->hasCurrencyColumns()) {
            $this->form->fill([
                'currency_base_locked' => false,
                'store_currency' => $base,
                'currency_base_unit' => CurrencyUnit::defaultUnit($base),
                'currency_gold_price' => null,
                'currency_gold_unit' => 'gram',
            ]);

            $this->converter_to = $base;
            $this->converter_to_unit = CurrencyUnit::defaultUnit($base);
            $this->converter_from = $base === 'USD' ? 'CNY' : 'USD';
            $this->converter_from_unit = CurrencyUnit::defaultUnit($this->converter_from);

            return;
        }

        $this->form->fill([
            'currency_base_locked' => (bool) ($settings->currency_base_locked ?? false),
            'store_currency' => $settings->currency_base_locked ? ($settings->store_currency ?: $base) : $base,
            'currency_base_unit' => $settings->currency_base_locked ? ($settings->currency_base_unit ?: CurrencyUnit::defaultUnit($base)) : CurrencyUnit::defaultUnit($base),
            'currency_gold_price' => $settings->currency_gold_price,
            'currency_gold_unit' => $settings->currency_gold_unit ?: 'gram',
        ]);

        $this->converter_to = $base;
        $this->converter_to_unit = CurrencyUnit::defaultUnit($base);
        $this->converter_from = $base === 'USD' ? 'CNY' : 'USD';
        $this->converter_from_unit = CurrencyUnit::defaultUnit($this->converter_from);
    }

    public function getTitle(): string
    {
        return '货币设置';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('finance');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('基准货币')
                    ->description('未固定时，系统按站点默认语言选择基准货币；固定后才使用这里保存的货币和单位。')
                    ->schema([
                        Toggle::make('currency_base_locked')
                            ->label('固定基准货币')
                            ->helperText('关闭时：中文默认人民币，繁体中文（香港）默认港币，美式英语默认美元，并随站点语言变化。')
                            ->default(false)
                            ->live(),
                        Select::make('store_currency')
                            ->label('基准货币')
                            ->options(CurrencyUnit::currencyOptions())
                            ->searchable()
                            ->default(fn (): string => CurrencyUnit::baseCurrency())
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('currency_base_unit', CurrencyUnit::defaultUnit($state));
                            }),
                        Select::make('currency_base_unit')
                            ->label('基准货币单位')
                            ->options(fn (Get $get): array => CurrencyUnit::unitOptions($get('store_currency')))
                            ->default(fn (): string => CurrencyUnit::baseUnit()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        if (! $this->hasCurrencyColumns()) {
            $this->sendMigrationMissingNotification();

            return;
        }

        $settings = $this->settings();
        $state = $this->normalizedState($this->form->getState());

        $settings->update($state);
        $this->form->fill($state);

        Notification::make()
            ->title('货币设置已保存')
            ->success()
            ->send();
    }

    public function refreshRates(): void
    {
        if (! $this->hasCurrencyColumns()) {
            $this->sendMigrationMissingNotification();

            return;
        }

        $settings = $this->settings();
        $settings->update($this->normalizedState($this->form->getState()));

        app(CurrencyRateService::class)->refresh($settings->fresh());
        $this->mount();

        Notification::make()
            ->title('汇率和黄金价格已刷新')
            ->success()
            ->send();
    }

    public function convertCurrency(): void
    {
        if (! $this->hasCurrencyColumns()) {
            $this->converter_result = '货币字段尚未迁移，请先运行 php artisan migrate。';

            return;
        }

        $settings = $this->settings();
        $rates = $settings->currency_exchange_rates ?: [CurrencyUnit::baseCurrency() => 1];
        $amount = (float) ($this->converter_amount ?: 0);

        $fromCurrency = CurrencyUnit::normalizeCurrency($this->converter_from);
        $toCurrency = CurrencyUnit::normalizeCurrency($this->converter_to);
        $fromMajorAmount = $amount * CurrencyUnit::unitFactor($fromCurrency, $this->converter_from_unit);

        $majorResult = app(CurrencyRateService::class)->convert($fromMajorAmount, $fromCurrency, $toCurrency, $rates);

        if ($majorResult === null) {
            $this->converter_result = '缺少可用汇率';

            return;
        }

        $unitResult = $majorResult / max(0.000001, CurrencyUnit::unitFactor($toCurrency, $this->converter_to_unit));
        $this->converter_result = rtrim(rtrim(number_format($unitResult, 6, '.', ''), '0'), '.').' '.($this->unitOptions($toCurrency)[$this->converter_to_unit] ?? $this->converter_to_unit);
    }

    public function updatedConverterFrom(?string $currency): void
    {
        $this->converter_from_unit = CurrencyUnit::defaultUnit($currency);
    }

    public function updatedConverterTo(?string $currency): void
    {
        $this->converter_to_unit = CurrencyUnit::defaultUnit($currency);
    }

    /**
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        return CurrencyUnit::currencyOptions();
    }

    /**
     * @return array<string, string>
     */
    public function unitOptions(?string $currency): array
    {
        return CurrencyUnit::unitOptions($currency);
    }

    /**
     * @return array<int, array{code:string,name:string,rate:string}>
     */
    public function rateRows(): array
    {
        if (! $this->hasCurrencyColumns()) {
            $base = CurrencyUnit::baseCurrency();

            return [[
                'code' => $base,
                'name' => CurrencyUnit::currencyOptions()[$base] ?? $base,
                'rate' => '1',
            ]];
        }

        $rates = $this->settings()->currency_exchange_rates ?: [CurrencyUnit::baseCurrency() => 1];

        return collect($rates)
            ->sortKeys()
            ->map(fn ($rate, string $code): array => [
                'code' => $code,
                'name' => CurrencyUnit::currencyOptions()[$code] ?? $code,
                'rate' => rtrim(rtrim(number_format((float) $rate, 8, '.', ''), '0'), '.'),
            ])
            ->values()
            ->all();
    }

    public function baseSummary(): string
    {
        $currency = CurrencyUnit::baseCurrency();
        $unit = CurrencyUnit::baseUnit();

        return (CurrencyUnit::currencyOptions()[$currency] ?? $currency).' / '.(CurrencyUnit::unitOptions($currency)[$unit] ?? $unit);
    }

    public function ratesUpdatedAt(): string
    {
        if (! $this->hasCurrencyColumns()) {
            return '尚未迁移';
        }

        return $this->settings()->currency_rates_updated_at?->format('Y-m-d H:i') ?? '尚未刷新';
    }

    public function goldSummary(): string
    {
        if (! $this->hasCurrencyColumns()) {
            return '尚未迁移';
        }

        $settings = $this->settings();

        if (! $settings->currency_gold_price) {
            return '暂无价格';
        }

        $unit = $settings->currency_gold_unit === 'ounce' ? '金衡盎司' : '克';

        return rtrim(rtrim(number_format((float) $settings->currency_gold_price, 4, '.', ''), '0'), '.').' / '.$unit;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizedState(array $state): array
    {
        $locked = (bool) ($state['currency_base_locked'] ?? false);
        $base = $locked
            ? CurrencyUnit::normalizeCurrency($state['store_currency'] ?? CurrencyUnit::baseCurrency())
            : CurrencyUnit::baseCurrency();
        $unit = $locked
            ? (string) ($state['currency_base_unit'] ?? CurrencyUnit::defaultUnit($base))
            : CurrencyUnit::defaultUnit($base);

        if (! array_key_exists($unit, CurrencyUnit::unitOptions($base))) {
            $unit = CurrencyUnit::defaultUnit($base);
        }

        return [
            'currency_base_locked' => $locked,
            'store_currency' => $base,
            'currency_base_unit' => $unit,
        ];
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }

    private function hasCurrencyColumns(): bool
    {
        try {
            return DbSchema::hasTable('site_settings')
                && DbSchema::hasColumn('site_settings', 'currency_base_locked')
                && DbSchema::hasColumn('site_settings', 'currency_base_unit')
                && DbSchema::hasColumn('site_settings', 'currency_exchange_rates')
                && DbSchema::hasColumn('site_settings', 'currency_gold_price')
                && DbSchema::hasColumn('site_settings', 'currency_gold_unit')
                && DbSchema::hasColumn('site_settings', 'currency_rates_updated_at');
        } catch (\Throwable) {
            return false;
        }
    }

    private function sendMigrationMissingNotification(): void
    {
        Notification::make()
            ->title('货币数据库字段尚未迁移')
            ->body('请在服务器执行 php artisan migrate 后再刷新国际汇率与黄金快照。')
            ->danger()
            ->send();
    }
}
