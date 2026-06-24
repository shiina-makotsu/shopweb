<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\AdminAccess;
use App\Models\AiWorkflow;
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

class SupportAiSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '客服 AI 设置';
    protected static string|\UnitEnum|null $navigationGroup = 'AI';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;
    protected static ?int $navigationSort = 40;
    protected static ?string $slug = 'support-ai-settings';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->only([
            'support_ai_enabled',
            'support_ai_endpoint',
            'support_ai_api_key',
            'support_ai_model',
            'support_ai_idle_minutes',
            'support_ai_system_prompt',
            'support_ai_workflow_slug',
        ]));
    }

    public function getTitle(): string
    {
        return '客服 AI 设置';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('support');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('AI 接待规则')
                    ->description('客户等待人工客服接入时，前台会展示排队提示；达到预设分钟数后，启用 AI 安抚消息。')
                    ->schema([
                        Toggle::make('support_ai_enabled')->label('启用客服 AI 安抚')->default(false)->live(),
                        TextInput::make('support_ai_idle_minutes')
                            ->label('最长等待分钟数')
                            ->helperText('前台排队提示会使用这个时间；启用 AI 时，超过该时间会自动发送安抚消息。')
                            ->numeric()
                            ->minValue(1)
                            ->default(10),
                        TextInput::make('support_ai_endpoint')
                            ->label('客服 AI 接口地址')
                            ->helperText('例如 OpenAI 兼容接口的完整 URL，保存后由客服 AI 调用逻辑读取。')
                            ->maxLength(500),
                        TextInput::make('support_ai_api_key')
                            ->label('客服 AI API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('support_ai_model')
                            ->label('客服 AI 模型标识')
                            ->helperText('例如 gpt-4.1-mini、deepseek-chat 或其他兼容模型名。')
                            ->maxLength(255),
                        Select::make('support_ai_workflow_slug')
                            ->label('客服 AI 工作流')
                            ->helperText('选择后可由工作流串联搜索、排序、回复等多个模型；留空时使用上方单模型配置。')
                            ->options(fn (): array => AiWorkflow::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'slug')
                                ->all())
                            ->searchable()
                            ->preload(),
                        Textarea::make('support_ai_system_prompt')
                            ->label('客服 AI 预设内容')
                            ->helperText('用于长时间没有客服接待时，对客户进行安抚或基础引导。')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $this->settings()->update($this->form->getState());

        Notification::make()->title('客服 AI 设置已保存')->success()->send();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }
}
