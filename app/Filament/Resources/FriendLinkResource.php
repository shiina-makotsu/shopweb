<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\FriendLinkResource\Pages\CreateFriendLink;
use App\Filament\Resources\FriendLinkResource\Pages\EditFriendLink;
use App\Filament\Resources\FriendLinkResource\Pages\ListFriendLinks;
use App\Models\FriendLink;
use App\Models\MediaAsset;
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
                    ->helperText('可以上传图片，也可以填写下方的外链图片 URL。编辑时若上传新文件，会优先使用上传内容。')
                    ->disk('public_uploads')
                    ->directory('friend-links')
                    ->image()
                    ->maxSize(5120)
                    ->openable()
                    ->downloadable(),
                TextInput::make('image_url')
                    ->label('链接图像 URL')
                    ->url()
                    ->maxLength(2048),
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
                ImageColumn::make('image_path')->label('图像')->state(fn (FriendLink $record): ?string => $record->imageUrl())->imageSize(40),
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareImageFormData(array $data): array
    {
        if (MediaAsset::isExternalUrl($data['image_path'] ?? null)) {
            $data['image_url'] = $data['image_path'];
            $data['image_path'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeImageFormData(array $data): array
    {
        $imagePath = $data['image_path'] ?? null;
        $imagePath = is_array($imagePath) ? reset($imagePath) : $imagePath;
        $imageUrl = trim((string) ($data['image_url'] ?? ''));
        unset($data['image_url']);

        if (is_string($imagePath) && blank($imagePath) === false) {
            $data['image_path'] = $imagePath;

            return $data;
        }

        if ($imageUrl !== '') {
            if (! MediaAsset::isExternalUrl($imageUrl)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image_url' => '请输入以 http:// 或 https:// 开头的图片链接。',
                ]);
            }

            $data['image_path'] = $imageUrl;
        }

        return $data;
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
