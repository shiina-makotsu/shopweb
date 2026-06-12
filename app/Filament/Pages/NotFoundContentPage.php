<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Models\MediaAsset;
use App\Models\Page as ContentPage;
use App\Support\AdminAccess;
use App\Support\PageTemplate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class NotFoundContentPage extends Page implements HasSchemas
{
    use HandlesPageCoverUpload;
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static ?string $navigationLabel = '404 页面';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static ?int $navigationSort = 12;
    protected static ?string $slug = 'not-found-content';
    protected string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $page = $this->pageRecord();

        $this->form->fill([
            'title' => $page->title ?: '页面不存在',
            'cover_media_asset_id' => $page->cover_media_asset_id,
            'body' => $page->body ?: PageTemplate::defaultBody(PageTemplate::NOT_FOUND),
            'excerpt' => $page->excerpt,
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
        ]);
    }

    public function getTitle(): string
    {
        return '404 页面';
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
                Section::make('404 页面内容')
                    ->description('访问不存在地址时展示的内容，保存后会写入自定义页面 slug=404，并自动启用 404 模板。')
                    ->schema([
                        TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        Select::make('cover_media_asset_id')
                            ->label('封面图')
                            ->helperText('可选择媒体库图片，也可以使用下面的上传或外部图片 URL。')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => PageResource::imageAssetOptions())
                            ->getSearchResultsUsing(fn (string $search): array => PageResource::imageAssetOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->find($value)?->name),
                        FileUpload::make('cover_upload')
                            ->label('上传新封面图')
                            ->disk('public_uploads')
                            ->directory('pages/covers')
                            ->image()
                            ->imagePreviewHeight('160')
                            ->panelLayout('compact')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        TextInput::make('cover_external_url')
                            ->label('封面图 URL')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->label('摘要/兜底说明')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('正文')
                    ->schema([
                        MarkdownEditor::make('body')
                            ->label('404 文案')
                            ->fileAttachmentsDisk('public_uploads')
                            ->fileAttachmentsDirectory('pages')
                            ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])
                            ->fileAttachmentsMaxSize(5120)
                            ->toolbarButtons([
                                ['bold', 'italic', 'strike', 'link'],
                                ['heading', 'blockquote', 'codeBlock'],
                                ['bulletList', 'orderedList', 'table'],
                                ['attachFiles'],
                                ['undo', 'redo'],
                            ])
                            ->minHeight('20rem')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('seo_title')->label('SEO 标题')->maxLength(255),
                        Textarea::make('seo_description')->label('SEO 描述')->rows(3)->maxLength(500)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->attachUploadedCover($this->form->getState());

        ContentPage::query()->updateOrCreate(
            ['slug' => '404'],
            [
                ...$data,
                'slug' => '404',
                'template' => PageTemplate::NOT_FOUND,
                'is_published' => true,
            ],
        );

        Notification::make()->title('404 页面已保存')->success()->send();
    }

    private function pageRecord(): ContentPage
    {
        return ContentPage::query()->firstOrNew([
            'slug' => '404',
        ], [
            'title' => '页面不存在',
            'template' => PageTemplate::NOT_FOUND,
            'body' => PageTemplate::defaultBody(PageTemplate::NOT_FOUND),
            'is_published' => true,
        ]);
    }
}
