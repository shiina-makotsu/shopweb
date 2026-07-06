<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AnnouncementCommentResource;
use App\Filament\Resources\ForumCommentResource;
use App\Filament\Resources\PageCommentResource;
use App\Filament\Resources\ProductCommentResource\Pages\ListProductComments;
use App\Models\ProductComment;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCommentResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ProductComment::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '评论管理';
    protected static ?string $modelLabel = '商品评论';
    protected static ?string $pluralModelLabel = '商品评论';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;
    protected static ?int $navigationSort = 20;

    public static function tabs(): array
    {
        return [
            'products' => ['label' => '商品评论', 'url' => static::getUrl('index')],
            'pages' => ['label' => '页面评论', 'url' => PageCommentResource::getUrl('index')],
            'announcements' => ['label' => '公告评论', 'url' => AnnouncementCommentResource::getUrl('index')],
            'forum' => ['label' => '论坛回复', 'url' => ForumCommentResource::getUrl('index')],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.title')->label('商品')->searchable(),
                TextColumn::make('user.public_id')->label('用户 ID')->searchable(),
                TextColumn::make('user.name')->label('用户昵称')->searchable(),
                TextColumn::make('rating')->label('评分')->sortable(),
                TextColumn::make('body')
                    ->label('内容')
                    ->limit(80)
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
                    ->action(fn (ProductComment $record) => $record->delete()),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductComments::route('/'),
        ];
    }
}
