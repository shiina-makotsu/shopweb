<?php

namespace App\Filament\Pages;

use App\Models\AiWorkflow;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Filament\Forms\Components\FileUpload;
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

class GuideAiSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '导购 AI';
    protected static string|\UnitEnum|null $navigationGroup = 'AI';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'guide-ai-settings';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->only([
            'guide_pet_enabled',
            'guide_pet_asset_path',
            'guide_pet_api_endpoint',
            'guide_pet_api_key',
            'guide_pet_model',
            'guide_pet_system_prompt',
            'guide_pet_context_mode',
            'guide_ai_workflow_slug',
        ]));
    }

    public function getTitle(): string
    {
        return '导购 AI';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('ai') || AdminAccess::can('settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('导购入口')
                    ->description('控制前台导购宠物、导购模型和默认上下文。')
                    ->schema([
                        Toggle::make('guide_pet_enabled')->label('启用导购网页宠物')->default(false),
                        $this->assetPathSelect('guide_pet_asset_path', '导购宠物资源', '可上传宠物图片或动效资源。'),
                        Select::make('guide_ai_workflow_slug')
                            ->label('导购工作流')
                            ->helperText('可选择 AI 工作流中的导购场景；留空时继续使用下方单模型配置。')
                            ->options(fn (): array => AiWorkflow::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->pluck('name', 'slug')
                                ->all())
                            ->searchable()
                            ->preload(),
                        Select::make('guide_pet_context_mode')->label('导购上下文')->options([
                            'storefront' => '前台页面',
                            'product' => '商品页',
                            'cart' => '购物车',
                        ])->default('storefront'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('兼容单模型配置')
                    ->description('未选择工作流时，导购功能会继续使用这组 URL、Key、模型和预设提示词。')
                    ->schema([
                        TextInput::make('guide_pet_api_endpoint')->label('AI 接口地址')->maxLength(500),
                        TextInput::make('guide_pet_api_key')->label('AI API Key')->password()->revealable()->maxLength(255),
                        TextInput::make('guide_pet_model')->label('AI 模型标识')->maxLength(255),
                        Textarea::make('guide_pet_system_prompt')->label('导购 AI 预设内容')->rows(6)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $this->settings()->update($this->form->getState());

        Notification::make()->title('导购 AI 设置已保存')->success()->send();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }

    private function assetPathSelect(string $name, string $label, string $helperText): Select
    {
        return Select::make($name)
            ->label($label)
            ->helperText($helperText)
            ->searchable()
            ->preload()
            ->options(fn (): array => MediaAsset::query()
                ->orderByDesc('id')
                ->limit(200)
                ->pluck('name', 'path')
                ->all())
            ->getSearchResultsUsing(fn (string $search): array => MediaAsset::query()
                ->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%");
                })
                ->orderByDesc('id')
                ->limit(50)
                ->pluck('name', 'path')
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->where('path', $value)->value('name') ?? $value)
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传文件')
                    ->disk('public_uploads')
                    ->directory('site')
                    ->maxSize(20480)
                    ->required(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('说明')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $path = $data['path'] ?? '';
                $path = is_array($path) ? (string) reset($path) : (string) $path;

                if ($path === '') {
                    return '';
                }

                $asset = MediaAsset::query()->firstOrCreate(
                    ['path' => $path],
                    [
                        'name' => $data['name'] ?: basename($path),
                        'alt' => $data['alt'] ?? null,
                        'usage' => MediaAsset::USAGE_RESOURCE,
                        'mime_type' => null,
                        'size' => null,
                    ],
                );

                return $asset->path;
            });
    }
}
