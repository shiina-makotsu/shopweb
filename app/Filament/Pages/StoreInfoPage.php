<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class StoreInfoPage extends Page
{
    protected static ?string $navigationLabel = '商店信息';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'store-info';
    protected string $view = 'filament.pages.store-info';

    public string $site_name = '';
    public ?string $store_email = null;
    public ?string $store_phone = null;
    public ?string $store_address = null;
    public ?string $store_tax_id = null;
    public ?string $store_country = null;
    public ?string $store_timezone = null;
    public string $store_currency = 'CNY';
    public string $primary_color = '#2D9CDB';
    public string $accent_color = '#F5A9B8';
    public string $background_color = '#FFF7FB';
    public ?string $editingField = null;

    public function mount(): void
    {
        $settings = $this->settings();

        foreach ($this->fields() as $field) {
            $this->{$field} = $settings->{$field} ?? $this->{$field};
        }
    }

    public function getTitle(): string
    {
        return '设置 - 商店信息';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    public function save(): void
    {
        $data = $this->normalizeDefaults($this->validate($this->rules()));

        $this->settings()->update($data);

        $this->editingField = null;

        Notification::make()
            ->title('商店信息已保存')
            ->success()
            ->send();
    }

    public function editField(string $field): void
    {
        if (! in_array($field, $this->fields(), true)) {
            return;
        }

        $this->editingField = $field;
    }

    public function cancelField(): void
    {
        $this->mount();
        $this->editingField = null;
    }

    public function saveField(string $field): void
    {
        if (! in_array($field, $this->fields(), true)) {
            return;
        }

        $data = $this->normalizeDefaults($this->validate([
            $field => $this->rules()[$field],
        ]));

        $this->settings()->update($data);

        $this->editingField = null;

        Notification::make()
            ->title('设置项已保存')
            ->success()
            ->send();
    }

    /**
     * @return array<int, array{field:string,label:string,type:string,multiline?:bool,placeholder?:string}>
     */
    public function settingRows(): array
    {
        return [
            ['field' => 'site_name', 'label' => '商店名称', 'type' => 'text'],
            ['field' => 'store_email', 'label' => '商店邮箱', 'type' => 'email'],
            ['field' => 'store_phone', 'label' => '商店电话', 'type' => 'text'],
            ['field' => 'store_address', 'label' => '商店地址', 'type' => 'textarea', 'multiline' => true],
            ['field' => 'store_tax_id', 'label' => '商店税号', 'type' => 'text'],
            ['field' => 'store_country', 'label' => '国家/地区', 'type' => 'text'],
            ['field' => 'store_timezone', 'label' => '商店时区', 'type' => 'text', 'placeholder' => 'Asia/Shanghai'],
            ['field' => 'store_currency', 'label' => '商店货币', 'type' => 'text'],
            ['field' => 'primary_color', 'label' => '后台/前台主色', 'type' => 'color'],
            ['field' => 'accent_color', 'label' => '强调色', 'type' => 'color'],
            ['field' => 'background_color', 'label' => '工作区背景色', 'type' => 'color'],
        ];
    }

    public function displayValue(string $field): string
    {
        $value = $this->{$field} ?? null;

        if (filled($value)) {
            return (string) $value;
        }

        return $this->defaults()[$field] ?? '-';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_tax_id' => ['nullable', 'string', 'max:255'],
            'store_country' => ['nullable', 'string', 'max:255'],
            'store_timezone' => ['nullable', 'string', 'max:255'],
            'store_currency' => ['nullable', 'string', 'max:10'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeDefaults(array $data): array
    {
        foreach ($this->defaults() as $field => $default) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = $default;
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'site_name' => config('app.name', 'ShopWeb'),
            'store_timezone' => config('app.timezone', 'Asia/Shanghai'),
            'store_currency' => 'CNY',
            'primary_color' => '#2D9CDB',
            'accent_color' => '#F5A9B8',
            'background_color' => '#FFF7FB',
        ];
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], [
            'site_name' => config('app.name', 'ShopWeb'),
            'store_timezone' => config('app.timezone', 'Asia/Shanghai'),
            'store_currency' => 'CNY',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function fields(): array
    {
        return [
            'site_name',
            'store_email',
            'store_phone',
            'store_address',
            'store_tax_id',
            'store_country',
            'store_timezone',
            'store_currency',
            'primary_color',
            'accent_color',
            'background_color',
        ];
    }
}
