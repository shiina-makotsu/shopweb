<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ForumThreadResource\Pages\ListForumThreads;
use App\Models\ForumThread;
use App\Services\ForumActivityLogger;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['comments' => fn (Builder $commentQuery): Builder => $commentQuery->visible()]))
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title', 'body'], $search))
                    ->sortable(),
                TextColumn::make('section.name')->label('版块')->sortable(),
                TextColumn::make('user.public_id')->label('贴主 ID')->searchable(),
                IconColumn::make('is_pinned')->label('置顶')->boolean(),
                IconColumn::make('is_featured')->label('星标')->boolean(),
                IconColumn::make('is_locked')->label('锁定')->boolean(),
                TextColumn::make('comments_count')->label('回复')->sortable(),
                TextColumn::make('likes_count')->label('点赞')->sortable(),
                TextColumn::make('views_count')->label('访问')->sortable(),
                TextColumn::make('shares_count')->label('转发')->sortable(),
                TextColumn::make('admin_note')->label('后台备注')->limit(24)->toggleable(),
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
                Action::make('toggleFeatured')
                    ->label(fn (ForumThread $record): string => $record->is_featured ? '取消星标' : '星标')
                    ->icon(Heroicon::OutlinedStar)
                    ->action(function (ForumThread $record): void {
                        $record->update(['is_featured' => ! $record->is_featured]);
                        app(ForumActivityLogger::class)->log($record->is_featured ? 'thread_featured_admin' : 'thread_unfeatured_admin', auth()->user(), $record, "后台切换星标：{$record->title}");
                    }),
                Action::make('toggleLock')
                    ->label(fn (ForumThread $record): string => $record->is_locked ? '解锁' : '锁帖')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('warning')
                    ->action(function (ForumThread $record): void {
                        $record->update(['is_locked' => ! $record->is_locked]);
                        app(ForumActivityLogger::class)->log($record->is_locked ? 'thread_locked_admin' : 'thread_unlocked_admin', auth()->user(), $record, "后台切换锁帖：{$record->title}");
                    }),
                Action::make('note')
                    ->label('备注')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->form([
                        Textarea::make('admin_note')->label('后台备注')->rows(4),
                    ])
                    ->fillForm(fn (ForumThread $record): array => ['admin_note' => $record->admin_note])
                    ->action(function (ForumThread $record, array $data): void {
                        $record->update(['admin_note' => $data['admin_note'] ?? null]);
                        app(ForumActivityLogger::class)->log('thread_noted_admin', auth()->user(), $record, "后台备注帖子：{$record->title}");
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
