<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ProductTagResource\Pages\CreateProductTag;
use App\Filament\Resources\ProductTagResource\Pages\EditProductTag;
use App\Filament\Resources\ProductTagResource\Pages\ListProductTags;
use App\Models\ProductTag;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductTagResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = ProductTag::class;
    protected static string $permissionArea = 'catalog';
    protected static ?string $navigationLabel = '商品标签';
    protected static ?string $modelLabel = '商品标签';
    protected static ?string $pluralModelLabel = '商品标签';
    protected static string|\UnitEnum|null $navigationGroup = '目录';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;
    protected static ?int $navigationSort = 18;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('名称')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('meta_title')->label('SEO 标题')->maxLength(255),
            Textarea::make('meta_description')->label('SEO 描述')->rows(3)->columnSpanFull(),
            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Toggle::make('is_active')->label('启用')->default(true),
            Select::make('private_shipping_default')
                ->label('私密发货默认值')
                ->options([
                    '' => '未设置',
                    '0' => '默认不选择私密发货',
                    '1' => '默认选择私密发货',
                ])
                ->dehydrateStateUsing(fn ($state): ?bool => $state === '' || $state === null ? null : $state === '1')
                ->formatStateUsing(fn ($state): string => $state === null ? '' : ((bool) $state ? '1' : '0')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('products'))
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name'], $search))
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['slug'], $search)),
                TextColumn::make('products_count')->counts('products')->label('商品数')->sortable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTags::route('/'),
            'create' => CreateProductTag::route('/create'),
            'edit' => EditProductTag::route('/{record}/edit'),
        ];
    }
}
