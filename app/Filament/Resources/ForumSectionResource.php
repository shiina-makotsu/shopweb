<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ForumSectionResource\Pages\CreateForumSection;
use App\Filament\Resources\ForumSectionResource\Pages\EditForumSection;
use App\Filament\Resources\ForumSectionResource\Pages\ListForumSections;
use App\Models\ForumSection;
use App\Models\User;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class ForumSectionResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ForumSection::class;
    protected static string $permissionArea = 'forum';
    protected static ?string $navigationLabel = '版块';
    protected static ?string $modelLabel = '版块';
    protected static ?string $pluralModelLabel = '版块';
    protected static string|\UnitEnum|null $navigationGroup = '论坛';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('版块')->schema([
                TextInput::make('name')->label('名称')->required()->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                Textarea::make('description')->label('说明')->rows(3)->columnSpanFull(),
                Select::make('moderators')
                    ->label('版主')
                    ->relationship(
                        name: 'moderators',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('role', 'customer')->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->displayName()} ({$record->public_id})")
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('可指定前台用户管理该版块，被指定用户的论坛身份会自动变为版主。')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('threads')->with('moderators'))
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name', 'slug', 'description'], $search))
                    ->sortable(),
                TextColumn::make('moderators.name')
                    ->label('版主')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->toggleable(),
                TextColumn::make('threads_count')->label('帖子数')->sortable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumSections::route('/'),
            'create' => CreateForumSection::route('/create'),
            'edit' => EditForumSection::route('/{record}/edit'),
        ];
    }
}
