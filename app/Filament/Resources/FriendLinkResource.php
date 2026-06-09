<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\FriendLinkResource\Pages\CreateFriendLink;
use App\Filament\Resources\FriendLinkResource\Pages\EditFriendLink;
use App\Filament\Resources\FriendLinkResource\Pages\ListFriendLinks;
use App\Models\FriendLink;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FriendLinkResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = FriendLink::class;
    protected static string $permissionArea = 'content';
    protected static ?string $navigationLabel = '友情链接';
    protected static ?string $modelLabel = '友情链接';
    protected static ?string $pluralModelLabel = '友情链接';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;
    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('链接信息')->schema([
                TextInput::make('site_name')->label('网站名称')->required()->maxLength(255),
                TextInput::make('url')->label('链接地址')->url()->required()->maxLength(500),
                FileUpload::make('image_path')
                    ->label('链接图像')
                    ->disk('public_uploads')
                    ->directory('friend-links')
                    ->image()
                    ->maxSize(5120)
                    ->openable()
                    ->downloadable(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
                Textarea::make('description')->label('链接介绍')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('图像')->disk('public_uploads')->imageSize(40),
                TextColumn::make('site_name')
                    ->label('网站名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['site_name', 'url', 'description'], $search))
                    ->sortable(),
                TextColumn::make('url')->label('链接')->limit(40)->toggleable(),
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
            'index' => ListFriendLinks::route('/'),
            'create' => CreateFriendLink::route('/create'),
            'edit' => EditFriendLink::route('/{record}/edit'),
        ];
    }
}
