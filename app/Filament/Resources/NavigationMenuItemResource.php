<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\NavigationMenuItemResource\Pages\CreateNavigationMenuItem;
use App\Filament\Resources\NavigationMenuItemResource\Pages\EditNavigationMenuItem;
use App\Filament\Resources\NavigationMenuItemResource\Pages\ListNavigationMenuItems;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Support\RegexSearch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NavigationMenuItemResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = NavigationMenuItem::class;
    protected static string $permissionArea = 'content';
    protected static ?string $navigationLabel = '前台菜单';
    protected static ?string $modelLabel = '前台菜单';
    protected static ?string $pluralModelLabel = '前台菜单';
    protected static string|\UnitEnum|null $navigationGroup = '内容';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('菜单项')->schema([
                Select::make('placement')
                    ->label('显示位置')
                    ->options(NavigationMenuItem::placementOptions())
                    ->default(NavigationMenuItem::PLACEMENT_TOP_NAV)
                    ->required()
                    ->live(),
                Select::make('parent_id')
                    ->label('上级菜单')
                    ->options(fn (callable $get, ?NavigationMenuItem $record): array => NavigationMenuItem::query()
                        ->whereNull('parent_id')
                        ->where('placement', $get('placement') ?: NavigationMenuItem::PLACEMENT_TOP_NAV)
                        ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->pluck('label', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->helperText('可创建没有页面/链接的上级菜单，用来承载二级菜单。没有子菜单的无页面菜单不会在前台显示。'),
                TextInput::make('label')->label('显示文字')->required()->maxLength(255),
                Select::make('route_name')
                    ->label('内置功能')
                    ->options(NavigationMenuItem::routeOptions())
                    ->searchable()
                    ->helperText('选择内置功能后会自动生成站内相对路径；需要链接到自定义页面时选择“自定义页面”并填写页面 Slug。'),
                Select::make('route_parameters.page')
                    ->label('自定义页面')
                    ->options(fn (): array => Page::query()->menuable()->orderBy('sort_order')->orderBy('title')->pluck('title', 'slug')->all())
                    ->searchable()
                    ->preload()
                    ->visible(fn (callable $get): bool => $get('route_name') === 'pages.show')
                    ->helperText('链接到后台“自定义页面”中的某个页面。'),
                TextInput::make('url')->label('自定义 URL')->maxLength(500)->helperText('可填写外部链接或站内路径，如 /products。留空时使用内置功能。'),
                KeyValue::make('route_parameters')
                    ->label('路由参数')
                    ->keyLabel('参数名')
                    ->valueLabel('参数值')
                    ->visible(fn (callable $get): bool => filled($get('route_name')) && $get('route_name') !== 'pages.show')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
                Toggle::make('opens_new_tab')->label('新窗口打开')->default(false),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('菜单')
                    ->state(fn (NavigationMenuItem $record): string => $record->treeLabel())
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['label', 'url', 'route_name'], $search))
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('显示位置')
                    ->formatStateUsing(fn (?string $state): string => NavigationMenuItem::placementOptions()[$state] ?? '顶部导航')
                    ->badge()
                    ->sortable(),
                TextColumn::make('parent.label')->label('上级')->toggleable(),
                TextColumn::make('route_name')->label('类型')->state(fn (NavigationMenuItem $record): string => $record->typeLabel())->toggleable(),
                TextColumn::make('url')->label('URL')->limit(36)->toggleable(),
                TextColumn::make('sort_order')->label('排序')->sortable(),
                TextColumn::make('is_active')->label('状态')->formatStateUsing(fn (bool $state): string => $state ? '启用' : '停用')->badge(),
                TextColumn::make('updated_at')->label('更新')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('placement')
                ->orderByRaw('coalesce(parent_id, id)')
                ->orderByRaw('case when parent_id is null then 0 else 1 end')
                ->orderBy('sort_order')
                ->orderBy('label'))
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNavigationMenuItems::route('/'),
            'create' => CreateNavigationMenuItem::route('/create'),
            'edit' => EditNavigationMenuItem::route('/{record}/edit'),
        ];
    }
}
