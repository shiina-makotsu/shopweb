<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Services\TelegramBotService;
use App\Support\AdminAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelegramBotSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $navigationLabel = 'Telegram 机器人';

    protected static string|\UnitEnum|null $navigationGroup = '客服';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'telegram-bot-settings';

    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->fillForm($this->settings());
    }

    public function getTitle(): string
    {
        return 'Telegram 机器人';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureWebhook')
                ->label('保存并注册 Webhook')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->configureWebhook()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('机器人配置')
                    ->schema([
                        Toggle::make('telegram_bot_enabled')
                            ->label('启用 Telegram 机器人')
                            ->default(false),
                        TextInput::make('telegram_bot_token')
                            ->label('BotFather Token')
                            ->password()
                            ->autocomplete('new-password')
                            ->default('')
                            ->placeholder('留空保留已保存的 Token')
                            ->regex('/^[0-9]+:[A-Za-z0-9_-]+$/D')
                            ->maxLength(4096),
                        TextInput::make('telegram_bot_username')
                            ->label('机器人用户名')
                            ->prefix('@')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('telegram_webhook_registered_at')
                            ->label('Webhook 注册时间')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $state = $this->form->getState();
            $settings = DB::transaction(function () use ($state): SiteSetting {
                $settings = SiteSetting::query()->lockForUpdate()->findOrFail($this->settings()->getKey());
                $token = trim((string) ($state['telegram_bot_token'] ?? ''));
                $tokenReplaced = $token !== '' && $token !== $settings->telegram_bot_token;
                $changes = ['telegram_bot_enabled' => (bool) ($state['telegram_bot_enabled'] ?? false)];

                if ($changes['telegram_bot_enabled'] && $token === '' && blank($settings->telegram_bot_token)) {
                    throw ValidationException::withMessages([
                        'data.telegram_bot_token' => '启用机器人前请填写 BotFather Token。',
                    ]);
                }

                if ($tokenReplaced) {
                    $changes['telegram_bot_token'] = $token;
                    $changes['telegram_webhook_secret'] = Str::random(64);
                    $changes['telegram_bot_username'] = null;
                    $changes['telegram_webhook_registered_at'] = null;
                } elseif ($changes['telegram_bot_enabled'] && blank($settings->telegram_webhook_secret)) {
                    $changes['telegram_webhook_secret'] = Str::random(64);
                }

                $settings->update($changes);

                if ($tokenReplaced) {
                    DB::table('telegram_message_links')->delete();
                    DB::table('telegram_updates')->delete();
                }

                return $settings;
            });

            $this->fillForm($settings->refresh());
        } finally {
            // Livewire must never return the submitted token in its next snapshot.
            $this->data['telegram_bot_token'] = '';
        }

        Notification::make()->title('Telegram 设置已保存')->success()->send();
    }

    public function configureWebhook(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->save();

        if (! $this->settings()->telegram_bot_enabled) {
            Notification::make()->title('请先启用 Telegram 机器人')->warning()->send();

            return;
        }

        $url = (string) config('app.url');

        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            Notification::make()->title('Webhook 需要公开 HTTPS 站点地址')->danger()->send();

            return;
        }

        try {
            app(TelegramBotService::class)->configureWebhook();

            Notification::make()->title('Webhook 注册成功')->success()->send();
        } catch (Throwable) {
            // Bot API exceptions can contain the token-bearing request URL.
            Notification::make()
                ->title('Webhook 注册失败')
                ->body('设置已保存，请检查 Token、公开 HTTPS 地址和 Telegram 网络连通性后重试。')
                ->danger()
                ->send();
        }

        $this->fillForm($this->settings());
    }

    private function fillForm(SiteSetting $settings): void
    {
        $this->form->fill([
            'telegram_bot_enabled' => (bool) $settings->telegram_bot_enabled,
            'telegram_bot_token' => '',
            'telegram_bot_username' => $settings->telegram_bot_username,
            'telegram_webhook_registered_at' => $settings->telegram_webhook_registered_at?->format('Y-m-d H:i:s'),
        ]);
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }
}
