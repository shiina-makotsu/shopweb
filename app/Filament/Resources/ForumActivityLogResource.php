<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ForumActivityLogResource\Pages\ListForumActivityLogs;
use App\Models\ForumActivityLog;
use App\Support\RegexSearch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ForumActivityLogResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ForumActivityLog::class;
    protected static string $permissionArea = 'forum';
    protected static ?string $navigationLabel = '操作记录';
    protected static ?string $modelLabel = '论坛操作记录';
    protected static ?string $pluralModelLabel = '论坛操作记录';
    protected static string|\UnitEnum|null $navigationGroup = '论坛';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('actor.public_id')->label('操作人')->searchable(),
                TextColumn::make('action')
                    ->label('操作')
                    ->state(fn (ForumActivityLog $record): string => $record->actionLabel())
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')->label('版块')->sortable(),
                TextColumn::make('thread.title')->label('帖子')->limit(32)->searchable(),
                TextColumn::make('targetUser.public_id')->label('目标用户')->toggleable(),
                TextColumn::make('summary')
                    ->label('说明')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['summary', 'action'], $search))
                    ->limit(64),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumActivityLogs::route('/'),
        ];
    }
}
