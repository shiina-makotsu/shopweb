<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\MediaAsset;
use App\Models\Page;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
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
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_published')->label('发布')->default(false),
                Textarea::make('excerpt')->label('摘要')->rows(3)->maxLength(1000)->columnSpanFull(),
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
                    ->disk('public_uploads')
                    ->imageSize(48)
                    ->defaultImageUrl(asset('favicon.ico')),
                TextColumn::make('title')->label('标题')->searchable(),
                TextColumn::make('slug')->label('Slug'),
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

    private static function imageAssetOptions(?string $search = null): array
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
