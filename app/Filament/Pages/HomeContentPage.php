<?php

namespace App\Filament\Pages;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Support\AdminAccess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
        $this->form->fill($this->settings()->only([
            'home_welcome_enabled',
            'home_title',
            'home_welcome_image_path',
            'home_content',
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
            ]);
    }

    public function save(): void
    {
        $this->settings()->update($this->form->getState());

        Notification::make()->title('首页已保存')->success()->send();
    }

    private function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
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
                    ->disk('public_uploads')
                    ->directory('site')
                    ->image()
                    ->maxSize(5120)
                    ->required(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $path = is_array($data['path']) ? reset($data['path']) : $data['path'];

                $asset = MediaAsset::query()->create([
                    'name' => $data['name'] ?: pathinfo($path, PATHINFO_FILENAME),
                    'path' => $path,
                    'disk' => 'public_uploads',
                    'alt' => $data['alt'] ?? null,
                    'usage' => MediaAsset::USAGE_HOME,
                ]);

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
