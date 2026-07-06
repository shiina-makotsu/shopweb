<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\AdminAccess;
use App\Support\LoadingPage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class LoadingPageSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '加载等待页';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?int $navigationSort = 13;
    protected static ?string $slug = 'loading-page-settings';
    protected string $view = 'filament.pages.loading-page-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->loadingPageConfig());
    }

    public function getTitle(): string
    {
        return '加载等待页';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('content');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('基础文本')
                    ->description('首次访问等待页会在用户第一次进入前台时出现。这里的文本会同步用于前台实际页面和右侧预览。')
                    ->schema([
                        TextInput::make('title')->label('标题')->required()->maxLength(120)->live(onBlur: true),
                        Textarea::make('subtitle')->label('说明文本')->rows(3)->required()->maxLength(500)->live(onBlur: true)->columnSpanFull(),
                        TextInput::make('status_text')->label('加载状态文本')->required()->maxLength(120)->live(onBlur: true),
                        TextInput::make('done_text')->label('完成状态文本')->required()->maxLength(120)->live(onBlur: true),
                        TextInput::make('skip_text')->label('跳过链接文本')->required()->maxLength(60)->live(onBlur: true),
                        Select::make('symbol')
                            ->label('旋转符号图案')
                            ->options(LoadingPage::symbolOptions())
                            ->required()
                            ->live(),
                        Select::make('progress_style')
                            ->label('进度条样式')
                            ->options(LoadingPage::progressStyleOptions())
                            ->required()
                            ->default('soft_gradient')
                            ->live(),
                        TextInput::make('layout_columns')
                            ->label('网格列数')
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(12)
                            ->default(6)
                            ->required()
                            ->live(onBlur: true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('网格组件')
                    ->description('拖拽组件条目可以调整渲染顺序；列、行、宽、高决定组件放在哪个网格中。文本、加载符号、进度条都可以作为独立组件移动。')
                    ->schema([
                        Repeater::make('components')
                            ->label('组件')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => LoadingPage::componentOptions()[$state['type'] ?? ''] ?? '组件')
                            ->schema([
                                Select::make('type')
                                    ->label('组件')
                                    ->options(LoadingPage::componentOptions())
                                    ->required()
                                    ->live(),
                                TextInput::make('label')
                                    ->label('符号旁标签')
                                    ->maxLength(80)
                                    ->visible(fn ($get): bool => $get('type') === 'symbol')
                                    ->live(onBlur: true),
                                Select::make('align')
                                    ->label('对齐')
                                    ->options([
                                        'left' => '左对齐',
                                        'center' => '居中',
                                        'right' => '右对齐',
                                        'stretch' => '拉伸',
                                    ])
                                    ->default('left')
                                    ->required()
                                    ->live(),
                                TextInput::make('x')->label('列')->numeric()->minValue(1)->maxValue(12)->default(1)->required()->live(onBlur: true),
                                TextInput::make('y')->label('行')->numeric()->minValue(1)->maxValue(12)->default(1)->required()->live(onBlur: true),
                                TextInput::make('w')->label('宽')->numeric()->minValue(1)->maxValue(12)->default(6)->required()->live(onBlur: true),
                                TextInput::make('h')->label('高')->numeric()->minValue(1)->maxValue(4)->default(1)->required()->live(onBlur: true),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $this->settings()->update([
            'loading_page_config' => LoadingPage::normalize($this->form->getState()),
        ]);

        Notification::make()->title('加载等待页已保存')->success()->send();
    }

    public function resetDefaults(): void
    {
        $this->form->fill(LoadingPage::defaults());
    }

    public function preview(): HtmlString
    {
        return LoadingPage::preview(LoadingPage::normalize($this->data));
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }
}
