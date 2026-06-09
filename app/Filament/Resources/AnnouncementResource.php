<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AnnouncementResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = Announcement::class;
    protected static string $permissionArea = 'content';
    protected static ?string $navigationLabel = '公告';
    protected static ?string $modelLabel = '公告';
    protected static ?string $pluralModelLabel = '公告';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;
    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('公告内容')->schema([
                TextInput::make('title')->label('标题')->required()->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                MarkdownEditor::make('body')
                    ->label('正文')
                    ->required()
                    ->fileAttachmentsDisk('public_uploads')
                    ->fileAttachmentsDirectory('announcements')
                    ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])
                    ->fileAttachmentsMaxSize(5120)
                    ->minHeight('22rem')
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('发布设置')->schema([
                Toggle::make('is_published')->label('发布')->default(true),
                Toggle::make('is_pinned')->label('置顶')->default(false),
                Toggle::make('comments_enabled')->label('允许评论')->default(false),
                Toggle::make('popup_when_unread')->label('未读时弹窗')->default(false),
                DateTimePicker::make('published_at')->label('发布时间')->seconds(false),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title', 'slug', 'body'], $search))
                    ->sortable(),
                IconColumn::make('is_published')->label('发布')->boolean(),
                IconColumn::make('is_pinned')->label('置顶')->boolean(),
                IconColumn::make('popup_when_unread')->label('弹窗')->boolean(),
                TextColumn::make('published_at')->label('发布时间')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('is_pinned', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
