<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\PageCommentResource\Pages\ListPageComments;
use App\Models\PageComment;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PageCommentResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = PageComment::class;
    protected static string $permissionArea = 'content';
    protected static ?string $navigationLabel = '页面评论';
    protected static ?string $modelLabel = '页面评论';
    protected static ?string $pluralModelLabel = '页面评论';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;
    protected static ?int $navigationSort = 18;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page.title')->label('页面')->limit(40)->searchable(),
                TextColumn::make('user.public_id')->label('用户 ID')->searchable(),
                TextColumn::make('user.name')->label('用户昵称')->searchable(),
                TextColumn::make('body')
                    ->label('内容')
                    ->limit(90)
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['body'], $search)),
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('delete')
                    ->label('删除')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (PageComment $record) => $record->update(['deleted_at' => now()])),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageComments::route('/'),
        ];
    }
}
