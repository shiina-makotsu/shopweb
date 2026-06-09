<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MailSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '邮件设置';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    protected static ?int $navigationSort = 18;
    protected static ?string $slug = 'mail-settings';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->only([
            'mail_host',
            'mail_port',
            'mail_encryption',
            'mail_username',
            'mail_password',
            'mail_from_address',
            'mail_from_name',
            'shipping_mail_subject',
            'shipping_mail_template',
        ]));
    }

    public function getTitle(): string
    {
        return '邮件设置';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('SMTP 与发货通知')->schema([
                    TextInput::make('mail_host')->label('SMTP 主机')->maxLength(255),
                    TextInput::make('mail_port')->label('SMTP 端口')->numeric(),
                    Select::make('mail_encryption')->label('加密')->options([
                        '' => '无',
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ]),
                    TextInput::make('mail_username')->label('SMTP 用户名')->maxLength(255),
                    TextInput::make('mail_password')->label('SMTP 密码')->password()->revealable()->maxLength(255),
                    TextInput::make('mail_from_address')->label('发件邮箱')->email()->maxLength(255),
                    TextInput::make('mail_from_name')->label('发件名称')->maxLength(255),
                    TextInput::make('shipping_mail_subject')->label('发货邮件标题')->maxLength(255),
                    Textarea::make('shipping_mail_template')->label('发货邮件补充说明')->rows(5)->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $this->settings()->update($this->form->getState());

        Notification::make()->title('邮件设置已保存')->success()->send();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }
}
