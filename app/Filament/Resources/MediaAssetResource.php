<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\MediaAssetResource\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaAssetResource\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssetResource\Pages\ListMediaAssets;
use App\Filament\Resources\MediaAssetResource\Pages\ListPresentationAssets;
use App\Models\MediaAsset;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use function Filament\Support\original_request;

class MediaAssetResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = MediaAsset::class;
    protected static string $permissionArea = 'media';
    protected static ?string $navigationLabel = '媒体库';
    protected static ?string $modelLabel = '媒体';
    protected static ?string $pluralModelLabel = '媒体库';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;
    protected static ?int $navigationSort = 20;

    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('PPT/展示资料')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedPresentationChartLine)
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.presentation'))
                ->sort(30)
                ->url(static::getUrl('presentation')),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('资源文件')->schema([
                FileUpload::make('path')
                    ->label('文件')
                    ->helperText('上传本地文件，或在下方填写外部图片 URL。两者至少填写一个。')
                    ->disk('public_uploads')
                    ->directory('media')
                    ->acceptedFileTypes(self::acceptedFileTypes())
                    ->maxSize(20480)
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),
                TextInput::make('external_url')
                    ->label('外部图片 URL')
                    ->helperText('支持 http:// 或 https:// 图片链接。保存后会作为外部资源引用，不会下载到服务器。')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
                Select::make('usage')
                    ->label('用途')
                    ->options(MediaAsset::usageOptions())
                    ->default(MediaAsset::USAGE_GENERAL)
                    ->required(),
                Select::make('library')
                    ->label('资源库')
                    ->options(MediaAsset::libraryOptions())
                    ->default(MediaAsset::LIBRARY_SITE)
                    ->required(),
                Textarea::make('notes')->label('备注')->rows(3)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('文件信息')->schema([
                TextInput::make('mime_type')->label('MIME')->disabled()->dehydrated(false),
                TextInput::make('size')->label('大小（字节）')->disabled()->dehydrated(false),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('预览')
                    ->state(fn (MediaAsset $record): ?string => $record->isImage() ? $record->url() : null)
                    ->imageSize(56)
                    ->defaultImageUrl(asset('favicon.ico')),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('mime_type')->label('类型')->badge()->sortable(),
                TextColumn::make('library')
                    ->label('资源库')
                    ->formatStateUsing(fn (?string $state): string => MediaAsset::libraryOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('usage')
                    ->label('用途')
                    ->formatStateUsing(fn (?string $state): string => MediaAsset::usageOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('size')
                    ->label('大小')
                    ->formatStateUsing(fn ($state, MediaAsset $record): string => $record->sizeForHumans())
                    ->sortable(),
                TextColumn::make('path')
                    ->label('URL')
                    ->state(fn (MediaAsset $record): string => $record->url())
                    ->copyable()
                    ->copyableState(fn (MediaAsset $record): string => $record->url())
                    ->url(fn (MediaAsset $record): string => $record->url(), true)
                    ->limit(48),
                TextColumn::make('usage_status')
                    ->label('引用状态')
                    ->state(fn (MediaAsset $record): string => $record->usageSummary())
                    ->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('usage')->label('用途')->options(MediaAsset::usageOptions()),
                SelectFilter::make('library')->label('资源库')->options(MediaAsset::libraryOptions()),
                TernaryFilter::make('referenced')
                    ->label('引用状态')
                    ->trueLabel('已引用')
                    ->falseLabel('未使用')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereIn('id', self::referencedAssetIds()),
                        false: fn (Builder $query): Builder => $query->whereNotIn('id', self::referencedAssetIds()),
                    ),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    /**
     * @return array<int, string>
     */
    public static function acceptedFileTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
            'application/pdf',
            'application/zip',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareAssetFormData(array $data): array
    {
        if (MediaAsset::isExternalUrl($data['path'] ?? null)) {
            $data['external_url'] = $data['path'];
            $data['path'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeAssetFormData(array $data): array
    {
        $path = $data['path'] ?? null;
        $path = is_array($path) ? reset($path) : $path;

        if (! is_string($path) || blank($path)) {
            $path = MediaAsset::pathFromUploadOrUrl($data);
        }

        unset($data['external_url']);

        if (MediaAsset::isExternalUrl($path)) {
            $data['path'] = $path;
            $data['disk'] = 'external';
            $data['mime_type'] = ($data['mime_type'] ?? null) ?: 'image/external';
            $data['size'] = null;

            return $data;
        }

        $data['path'] = $path;
        $data['disk'] = $data['disk'] ?? 'public_uploads';

        return $data;
    }

    /**
     * @return array<int>
     */
    private static function referencedAssetIds(): array
    {
        return MediaAsset::query()
            ->get()
            ->filter(fn (MediaAsset $asset): bool => $asset->isReferenced())
            ->pluck('id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'presentation' => ListPresentationAssets::route('/presentation'),
            'create' => CreateMediaAsset::route('/create'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
