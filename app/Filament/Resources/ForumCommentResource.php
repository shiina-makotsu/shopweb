<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ForumCommentResource\Pages\ListForumComments;
use App\Models\ForumComment;
use App\Services\ForumActivityLogger;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumCommentResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ForumComment::class;
    protected static string $permissionArea = 'forum';
    protected static ?string $navigationLabel = '回复';
    protected static ?string $modelLabel = '回复';
    protected static ?string $pluralModelLabel = '回复';
    protected static string|\UnitEnum|null $navigationGroup = '论坛';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;
    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('thread.title')->label('帖子')->limit(40)->searchable(),
                TextColumn::make('user.public_id')->label('用户 ID')->searchable(),
                TextColumn::make('body')
                    ->label('内容')
                    ->limit(90)
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['body'], $search)),
                TextColumn::make('likes_count')->label('点赞')->sortable(),
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('delete')
                    ->label('删除')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ForumComment $record): void {
                        $record->update([
                            'deleted_at' => now(),
                            'deleted_by_id' => auth()->id(),
                        ]);
                        app(ForumActivityLogger::class)->log('comment_deleted_admin', auth()->user(), $record, '后台删除论坛回复');
                    }),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumComments::route('/'),
        ];
    }
}
