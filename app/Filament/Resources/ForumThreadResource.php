<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ForumThreadResource\Pages\ListForumThreads;
use App\Models\ForumThread;
use App\Services\ForumActivityLogger;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumThreadResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ForumThread::class;
    protected static string $permissionArea = 'forum';
    protected static ?string $navigationLabel = '帖子';
    protected static ?string $modelLabel = '帖子';
    protected static ?string $pluralModelLabel = '帖子';
    protected static string|\UnitEnum|null $navigationGroup = '论坛';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title', 'body'], $search))
                    ->sortable(),
                TextColumn::make('section.name')->label('版块')->sortable(),
                TextColumn::make('user.public_id')->label('贴主 ID')->searchable(),
                IconColumn::make('is_pinned')->label('置顶')->boolean(),
                TextColumn::make('likes_count')->label('点赞')->sortable(),
                TextColumn::make('shares_count')->label('转发')->sortable(),
                TextColumn::make('created_at')->label('发布时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('togglePin')
                    ->label(fn (ForumThread $record): string => $record->is_pinned ? '取消置顶' : '置顶')
                    ->icon(Heroicon::OutlinedChevronUp)
                    ->action(function (ForumThread $record): void {
                        $record->update(['is_pinned' => ! $record->is_pinned]);
                        app(ForumActivityLogger::class)->log($record->is_pinned ? 'thread_pinned_admin' : 'thread_unpinned_admin', auth()->user(), $record, "后台切换置顶：{$record->title}");
                    }),
                Action::make('delete')
                    ->label('删除')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ForumThread $record): void {
                        $record->update([
                            'deleted_at' => now(),
                            'deleted_by_id' => auth()->id(),
                        ]);
                        app(ForumActivityLogger::class)->log('thread_deleted_admin', auth()->user(), $record, "后台删除帖子：{$record->title}");
                    }),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumThreads::route('/'),
        ];
    }
}
