<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportQuickReplyResource\Pages\CreateSupportQuickReply;
use App\Filament\Resources\SupportQuickReplyResource\Pages\EditSupportQuickReply;
use App\Filament\Resources\SupportQuickReplyResource\Pages\ListSupportQuickReplies;
use App\Models\SupportQuickReply;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportQuickReplyResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportQuickReply::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '预设回复';
    protected static ?string $modelLabel = '预设回复';
    protected static ?string $pluralModelLabel = '预设回复';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('预设回复')->schema([
                TextInput::make('title')->label('标题')->required()->maxLength(255),
                TextInput::make('category')->label('分类')->maxLength(255),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
                Textarea::make('body')->label('回复内容')->required()->rows(6)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['title', 'category', 'body'], $search))
                    ->sortable(),
                TextColumn::make('category')->label('分类')->toggleable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('is_active')->label('状态')->formatStateUsing(fn (bool $state): string => $state ? '启用' : '停用')->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportQuickReplies::route('/'),
            'create' => CreateSupportQuickReply::route('/create'),
            'edit' => EditSupportQuickReply::route('/{record}/edit'),
        ];
    }
}
