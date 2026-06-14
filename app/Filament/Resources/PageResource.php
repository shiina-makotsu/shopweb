<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\MediaAsset;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Support\PageTemplate;
use App\Support\PageMenuPublication;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Page::class;
    protected static string $permissionArea = 'content';
    protected static ?string $navigationLabel = '自定义页面';
    protected static ?string $modelLabel = '页面';
    protected static ?string $pluralModelLabel = '自定义页面';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('页面信息')->schema([
                TextInput::make('title')->label('标题')->required()->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('使用 404 作为 Slug 时，该页面会作为站点 404 页面内容。')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('template')
                    ->label('页面模板')
                    ->options(PageTemplate::options())
                    ->default(PageTemplate::DEFAULT)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($title = PageTemplate::defaultTitle($state)) {
                            $set('title', $title);
                        }

                        if ($slug = PageTemplate::defaultSlug($state)) {
                            $set('slug', $slug);
                        }

                        if ($excerpt = PageTemplate::defaultExcerpt($state)) {
                            $set('excerpt', $excerpt);
                            $set('seo_description', $excerpt);
                        }

                        $set('body', PageTemplate::defaultBody($state));
                    })
                    ->helperText('功能模板会在前台自动渲染菜单、友链、搜索、资源发布、关于我们或 404 内容；正文可继续写自定义说明。'),
                Select::make('cover_media_asset_id')
                    ->label('封面图')
                    ->helperText('从媒体库选择已有图片；也可以使用下方上传框直接上传新封面。')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => self::imageAssetOptions())
                    ->getSearchResultsUsing(fn (string $search): array => self::imageAssetOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->find($value)?->name),
                FileUpload::make('cover_upload')
                    ->label('上传新封面图')
                    ->helperText('选择图片并保存页面后，会自动加入资源管理并设为页面封面。')
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
                    ->helperText('也可以直接填写 http:// 或 https:// 图片链接，保存后会加入媒体库并设为封面。')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                ToggleButtons::make('editor_mode')
                    ->label('编辑模式')
                    ->options([
                        'traditional' => '传统 Markdown',
                        'interactive' => '交互式区块',
                    ])
                    ->default('traditional')
                    ->required()
                    ->inline()
                    ->live(),
                Toggle::make('is_published')->label('发布')->default(false),
                Textarea::make('excerpt')->label('摘要')->rows(3)->maxLength(1000)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('菜单发布')->schema([
                Select::make('menu_placement')
                    ->label('发布到菜单')
                    ->options(['none' => '不添加到菜单'] + NavigationMenuItem::placementOptions())
                    ->default('none')
                    ->live()
                    ->helperText('发布页面前可以选择是否同步创建前台菜单项；404 模板页不会进入前台菜单。'),
                Select::make('menu_parent_id')
                    ->label('上级菜单')
                    ->options(fn (callable $get, ?Page $record): array => NavigationMenuItem::query()
                        ->whereNull('parent_id')
                        ->where('placement', $get('menu_placement') ?: NavigationMenuItem::PLACEMENT_TOP_NAV)
                        ->when($record, function ($query) use ($record): void {
                            $menu = PageMenuPublication::findForPage($record);

                            if ($menu) {
                                $query->whereKeyNot($menu->getKey());
                            }
                        })
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->pluck('label', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get): bool => ($get('menu_placement') ?? 'none') !== 'none')
                    ->helperText('留空时作为一级菜单；选择无页面上级菜单时会作为其二级菜单显示。'),
                TextInput::make('menu_label')
                    ->label('菜单文字')
                    ->maxLength(255)
                    ->visible(fn (callable $get): bool => ($get('menu_placement') ?? 'none') !== 'none')
                    ->helperText('留空时使用页面标题。'),
                TextInput::make('menu_sort_order')
                    ->label('菜单排序')
                    ->numeric()
                    ->default(0)
                    ->visible(fn (callable $get): bool => ($get('menu_placement') ?? 'none') !== 'none'),
            ])->columns(2)->columnSpanFull(),

            Section::make('Markdown 正文')->schema([
                MarkdownEditor::make('body')
                    ->label('正文')
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
                    ->minHeight('24rem')
                    ->helperText('支持标题、列表、表格、链接和图片。前台会安全渲染 Markdown，不直接执行原始 HTML。')
                    ->columnSpanFull(),
            ])
                ->visible(fn (callable $get): bool => ($get('editor_mode') ?? 'traditional') === 'traditional')
                ->columnSpanFull(),

            Section::make('交互式区块编辑')->schema([
                Builder::make('blocks')
                    ->label('页面区块')
                    ->helperText('传统模式可作为附加区块；交互式模式会将这里作为主编辑器。添加模块后可以拖拽调整顺序。')
                    ->blocks([
                        Block::make('heading')
                            ->label(fn (?array $state): string => filled($state['text'] ?? null) ? '标题：'.$state['text'] : '标题')
                            ->schema([
                                TextInput::make('text')->label('标题文字')->required()->maxLength(255),
                                Select::make('level')
                                    ->label('标题级别')
                                    ->options([
                                        'h2' => '二级标题',
                                        'h3' => '三级标题',
                                        'h4' => '四级标题',
                                    ])
                                    ->default('h2')
                                    ->required(),
                            ])->columns(2),
                        Block::make('paragraph')
                            ->label('段落 / Markdown')
                            ->schema([
                                Textarea::make('content')
                                    ->label('内容')
                                    ->rows(6)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                        Block::make('quote')
                            ->label('引用')
                            ->schema([
                                Textarea::make('content')->label('引用内容')->rows(4)->required()->columnSpanFull(),
                                TextInput::make('author')->label('来源 / 作者')->maxLength(255),
                            ]),
                        Block::make('image')
                            ->label(fn (?array $state): string => filled($state['caption'] ?? null) ? '图片：'.$state['caption'] : '图片')
                            ->schema([
                                TextInput::make('url')
                                    ->label('图片地址')
                                    ->required()
                                    ->maxLength(2048)
                                    ->helperText('支持站内相对路径或 http/https 图片地址。'),
                                TextInput::make('alt')->label('替代文字')->maxLength(255),
                                TextInput::make('caption')->label('图片说明')->maxLength(255)->columnSpanFull(),
                            ])->columns(2),
                        Block::make('button')
                            ->label(fn (?array $state): string => filled($state['label'] ?? null) ? '按钮：'.$state['label'] : '按钮')
                            ->schema([
                                TextInput::make('label')->label('按钮文字')->required()->maxLength(80),
                                TextInput::make('url')->label('链接地址')->required()->maxLength(2048),
                                Select::make('style')
                                    ->label('样式')
                                    ->options([
                                        'primary' => '主按钮',
                                        'secondary' => '次按钮',
                                    ])
                                    ->default('primary')
                                    ->required(),
                            ])->columns(3),
                        Block::make('notice')
                            ->label('提示框')
                            ->schema([
                                Select::make('type')
                                    ->label('类型')
                                    ->options([
                                        'info' => '信息',
                                        'success' => '成功',
                                        'warning' => '提醒',
                                        'danger' => '警告',
                                    ])
                                    ->default('info')
                                    ->required(),
                                Textarea::make('content')->label('内容')->rows(4)->required()->columnSpanFull(),
                            ]),
                        Block::make('columns')
                            ->label('双栏内容')
                            ->schema([
                                Textarea::make('left')->label('左栏')->rows(5),
                                Textarea::make('right')->label('右栏')->rows(5),
                            ])->columns(2),
                    ])
                    ->addActionLabel('添加区块')
                    ->blockNumbers(false)
                    ->blockIcons(false)
                    ->reorderableWithButtons()
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columnSpanFull(),

            Section::make('SEO')->schema([
                TextInput::make('seo_title')->label('SEO 标题')->maxLength(255),
                Textarea::make('seo_description')->label('SEO 描述')->rows(3)->maxLength(500)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('coverMediaAsset.path')
                    ->label('封面')
                    ->state(fn (Page $record): ?string => $record->coverMediaAsset?->isImage() ? $record->coverMediaAsset->url() : null)
                    ->imageSize(48)
                    ->defaultImageUrl('/favicon.ico'),
                TextColumn::make('title')->label('标题')->searchable(),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('template')
                    ->label('模板')
                    ->formatStateUsing(fn (?string $state): string => PageTemplate::label($state))
                    ->badge(),
                TextColumn::make('editor_mode')
                    ->label('编辑模式')
                    ->formatStateUsing(fn (?string $state): string => $state === 'interactive' ? '交互式' : '传统')
                    ->badge(),
                IconColumn::make('is_published')->label('发布')->boolean(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    public static function imageAssetOptions(?string $search = null): array
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
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->id => $asset->name ?: basename($asset->path)])
            ->all();
    }
}
