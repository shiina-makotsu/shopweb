<?php

namespace App\Filament\Pages;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PaymentSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '付款设置';
    protected static string|\UnitEnum|null $navigationGroup = '交易';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'payment-settings';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->only([
            'payment_qr_path',
            'payment_account_name',
            'payment_account_note',
            'payment_auto_check_enabled',
            'payment_pending_timeout_minutes',
            'payment_instructions',
            'payment_gateway_provider',
            'payment_enabled_methods',
            'payment_gateway_config',
            'payment_gateway_notes',
            'payment_fallback_config',
            'wallet_recharge_success_message',
        ]));
    }

    public function getTitle(): string
    {
        return '付款设置';
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
                Section::make('付款二维码与说明')
                    ->description('订单号由系统创建订单时自动生成，后台订单管理可按订单号查询对应用户。')
                    ->schema([
                    self::imagePathSelect('payment_qr_path', '付款二维码', '上传支付宝/收款二维码，用户下单后会在付款页看到。'),
                    TextInput::make('payment_account_name')->label('收款名称')->maxLength(255),
                    TextInput::make('payment_account_note')->label('付款备注提示')->maxLength(255),
                    Toggle::make('payment_auto_check_enabled')->label('上传截图后自动初审')->default(true),
                    TextInput::make('payment_pending_timeout_minutes')
                        ->label('待付款订单超时关闭')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(10)
                        ->suffix('分钟')
                        ->helperText('用户创建订单后若未提交付款凭证，会在该时间后自动关闭并从用户订单列表隐藏；对接支付 API 自动确认收款的订单不受影响。'),
                    MarkdownEditor::make('payment_instructions')
                        ->label('付款说明')
                        ->fileAttachmentsDisk('public_uploads')
                        ->fileAttachmentsDirectory('site')
                        ->minHeight('20rem')
                        ->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),
                Section::make('支付接口预留')->schema([
                    Select::make('payment_gateway_provider')->label('默认支付提供方')->options([
                        'manual' => '人工转账 / 二维码',
                        'paypal' => 'PayPal（预留）',
                        'stripe' => '卡支付网关（预留）',
                        'custom' => '自定义接口（预留）',
                    ])->default('manual'),
                    Select::make('payment_enabled_methods')->label('预留支付方式')->multiple()->options([
                        'alipay_qr' => '支付宝二维码',
                        'wechat_qr' => '微信二维码',
                        'paypal' => 'PayPal',
                        'visa' => 'Visa',
                        'mastercard' => 'Mastercard',
                        'amex' => 'American Express',
                    ]),
                    TextInput::make('payment_gateway_config.api_endpoint')->label('接口地址')->maxLength(500),
                    TextInput::make('payment_gateway_config.client_id')->label('Client ID / 商户号')->maxLength(255),
                    TextInput::make('payment_gateway_config.secret_key_hint')->label('密钥备注')->maxLength(255),
                    TextInput::make('payment_gateway_config.paypal_email')->label('PayPal 收款邮箱')->email()->maxLength(255),
                    Textarea::make('payment_gateway_notes')->label('接口备注')->rows(3)->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),
                Section::make('备用付款方案')
                    ->description('用于主付款码被风控、加载失败或无法完成支付时展示。用户提交口令红包后，后台订单详情会显示文字凭证，管理员可人工确认付款。')
                    ->schema([
                        self::imagePathSelect('payment_fallback_config.fallback_qr_path', '备用付款码', '主付款码不可用时显示的备用收款码。'),
                        self::imagePathSelect('payment_fallback_config.friend_qr_path', '好友码 / 联系码', '可填写支付宝好友码、微信联系码或其他联系二维码。'),
                        Toggle::make('payment_fallback_config.password_red_packet_enabled')->label('允许提交口令红包')->default(false),
                        Textarea::make('payment_fallback_config.password_red_packet_note')
                            ->label('口令红包说明')
                            ->rows(3)
                            ->helperText('例如：支付失败时可填写支付宝口令红包，提交后等待后台人工确认。')
                            ->columnSpanFull(),
                        Toggle::make('payment_fallback_config.wallet_enabled')->label('显示钱包充值占位')->default(false),
                        Textarea::make('payment_fallback_config.wallet_note')
                            ->label('钱包充值说明')
                            ->rows(3)
                            ->helperText('钱包扣款链路暂作为预留说明，可引导用户用兑换码或联系客服充值。')
                            ->columnSpanFull(),
                        Toggle::make('payment_fallback_config.support_enabled')->label('显示联系客服兜底')->default(true),
                    ])->columns(2)->columnSpanFull(),
                Section::make('钱包充值')
                    ->schema([
                        Textarea::make('wallet_recharge_success_message')
                            ->label('充值成功弹窗文案')
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('后台确认充值订单收款后，用户打开该充值订单时会看到这段提示。')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $this->settings()->update($this->form->getState());

        Notification::make()->title('付款设置已保存')->success()->send();
    }

    private static function imagePathSelect(string $name, string $label, string $helperText): Select
    {
        return Select::make($name)
            ->label($label)
            ->helperText($helperText)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::imageOptions())
            ->getSearchResultsUsing(fn (string $search): array => self::imageOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->where('path', $value)->value('name') ?? $value)
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传图片')
                    ->helperText('上传图片，或填写下方外部图片 URL。')
                    ->disk('public_uploads')
                    ->directory('payments')
                    ->image()
                    ->maxSize(5120),
                TextInput::make('external_url')
                    ->label('外部图片 URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $asset = MediaAsset::createImageFromUploadOrUrl($data, MediaAsset::USAGE_GENERAL);

                return $asset->path;
            });
    }

    private static function imageOptions(?string $search = null): array
    {
        return MediaAsset::query()
            ->where(function ($query): void {
                $query->where('mime_type', 'like', 'image/%')->orWhereNull('mime_type');
            })
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name ?: basename($asset->path)])
            ->all();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }
}
