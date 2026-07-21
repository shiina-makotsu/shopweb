<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportContactMethodResource\Pages\CreateSupportContactMethod;
use App\Filament\Resources\SupportContactMethodResource\Pages\EditSupportContactMethod;
use App\Filament\Resources\SupportContactMethodResource\Pages\ListSupportContactMethods;
use App\Models\SupportContactMethod;
use App\Support\FontAwesome;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SupportContactMethodResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportContactMethod::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '联系方式';
    protected static ?string $modelLabel = '联系方式';
    protected static ?string $pluralModelLabel = '联系方式';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;
    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('联系方式设置')->schema([
                TextInput::make('name')->label('联系软件名称')->required()->maxLength(255),
                TextInput::make('account')->label('账号 / 群号')->maxLength(255),
                TextInput::make('url')
                    ->label('跳转链接')
                    ->required()
                    ->maxLength(2048)
                    ->rules(['regex:/^(?:https?|mailto|tel|sms|tg|discord|weixin|mqqapi):/i'])
                    ->helperText('支持网页链接、邮件、电话及常见聊天软件深链接；危险协议不会在前台输出。')
                    ->columnSpanFull(),
                Select::make('icon')
                    ->label('Font Awesome 图标')
                    ->options(static::iconOptions())
                    ->allowHtml()
                    ->searchable()
                    ->default('fa-solid fa-comments')
                    ->required(),
                TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Toggle::make('is_active')->label('启用')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('图标')
                    ->state(fn (SupportContactMethod $record): HtmlString => new HtmlString(
                        '<i class="'.e(FontAwesome::normalizeClasses($record->icon) ?: 'fa-solid fa-comments').' fa-fw" aria-hidden="true"></i>',
                    ))
                    ->html(),
                TextColumn::make('name')->label('软件')->searchable()->sortable(),
                TextColumn::make('account')->label('账号 / 群号')->searchable()->placeholder('-'),
                TextColumn::make('url')->label('链接')->limit(45)->url(fn (SupportContactMethod $record): ?string => $record->linkData()['url'] ?? null, true),
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
            'index' => ListSupportContactMethods::route('/'),
            'create' => CreateSupportContactMethod::route('/create'),
            'edit' => EditSupportContactMethod::route('/{record}/edit'),
        ];
    }

    private static function iconOptions(): array
    {
        return collect(FontAwesome::contactIconOptions())
            ->mapWithKeys(fn (string $label, string $classes): array => [
                $classes => '<span class="inline-flex items-center gap-2"><i class="'.e($classes).' fa-fw" aria-hidden="true"></i><span>'.e($label).'</span></span>',
            ])
            ->all();
    }
}
