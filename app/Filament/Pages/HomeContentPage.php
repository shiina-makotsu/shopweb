<?php

namespace App\Filament\Pages;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Services\StorefrontCache;
use App\Support\AdminAccess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HomeContentPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '首页';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'home-content';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $settings = $this->settings();

        $this->form->fill(array_merge($settings->only([
            'home_welcome_enabled',
            'home_title',
            'home_welcome_image_path',
            'home_content',
        ]), [
            'home_product_section_order' => collect($settings->homeProductSectionOrder())
                ->map(fn (string $section): array => ['section' => $section])
                ->all(),
        ]));
    }

    public function getTitle(): string
    {
        return '首页';
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
                Tabs::make('首页设置')
                    ->tabs([
                        Tab::make('欢迎区')
                            ->schema([
                                Section::make('首页欢迎区')->schema([
                                    Toggle::make('home_welcome_enabled')
                                        ->label('显示首页欢迎区')
                                        ->default(true),
                                    TextInput::make('home_title')->label('欢迎区标题')->maxLength(255),
                                    self::imagePathSelect('home_welcome_image_path', '欢迎区图片', '可从资源管理选择已有图片；也可以点击加号直接上传新图片。'),
                                    MarkdownEditor::make('home_content')
                                        ->label('欢迎区正文')
                                        ->fileAttachmentsDisk('public_uploads')
                                        ->fileAttachmentsDirectory('site')
                                        ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])
                                        ->fileAttachmentsMaxSize(5120)
                                        ->toolbarButtons([
                                            ['bold', 'italic', 'strike', 'link'],
                                            ['heading', 'blockquote', 'codeBlock'],
                                            ['bulletList', 'orderedList', 'table'],
                                            ['attachFiles'],
                                            ['undo', 'redo'],
                                        ])
                                        ->minHeight('24rem')
                                        ->helperText('支持 Markdown，可用附件按钮插入图片。关闭欢迎区后，该区块不会显示在首页。')
                                        ->columnSpanFull(),
                                ])->columns(2)->columnSpanFull(),
                            ]),
                        Tab::make('商品栏顺序')
                            ->schema([
                                Section::make('首页商品栏顺序')
                                    ->description('拖动下方条目即可调整首页商品栏显示顺序。折扣商品为空时不会显示；其他栏目按配置顺序渲染。')
                                    ->schema([
                                        Repeater::make('home_product_section_order')
                                            ->label('商品栏')
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable()
                                            ->itemLabel(fn (array $state): ?string => SiteSetting::homeProductSectionLabels()[$state['section'] ?? ''] ?? '商品栏')
                                            ->schema([
                                                Select::make('section')
                                                    ->label('商品栏')
                                                    ->options(SiteSetting::homeProductSectionLabels())
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->required(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['home_product_section_order'] = $this->normalizeProductSectionOrder($state['home_product_section_order'] ?? []);

        $this->settings()->update($state);
        app(StorefrontCache::class)->clear();

        Notification::make()->title('首页已保存')->success()->send();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, string>
     */
    private function normalizeProductSectionOrder(array $sections): array
    {
        $allowed = array_keys(SiteSetting::homeProductSectionLabels());
        $ordered = [];

        foreach ($sections as $item) {
            $section = is_array($item) ? ($item['section'] ?? null) : $item;

            if (is_string($section) && in_array($section, $allowed, true) && ! in_array($section, $ordered, true)) {
                $ordered[] = $section;
            }
        }

        foreach (SiteSetting::defaultHomeProductSectionOrder() as $section) {
            if (! in_array($section, $ordered, true)) {
                $ordered[] = $section;
            }
        }

        return $ordered;
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
                    ->directory('site')
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
                $asset = MediaAsset::createImageFromUploadOrUrl($data, MediaAsset::USAGE_HOME);

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
}
