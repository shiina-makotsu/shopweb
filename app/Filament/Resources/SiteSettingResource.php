<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SiteSettingResource\Pages\EditSiteSetting;
use App\Filament\Resources\SiteSettingResource\Pages\ListSiteSettings;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SiteSettingResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SiteSetting::class;
    protected static string $permissionArea = 'settings';
    protected static ?string $navigationLabel = '站点设置';
    protected static ?string $modelLabel = '站点设置';
    protected static ?string $pluralModelLabel = '站点设置';
    protected static string|\UnitEnum|null $navigationGroup = '系统';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('基础信息')
                ->description('站点名称、联系方式和页脚文案等全局信息。')
                ->schema([
                TextInput::make('site_name')
                    ->label('站点名称')
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => filled($state) ? trim((string) $state) : config('app.name', 'ShopWeb')),
                TextInput::make('store_email')->label('商店邮箱')->email()->maxLength(255),
                TextInput::make('store_phone')->label('商店电话')->maxLength(255),
                TextInput::make('store_address')->label('商店地址')->maxLength(255)->columnSpanFull(),
                TextInput::make('store_country')->label('国家/地区')->maxLength(255),
                TextInput::make('store_timezone')->label('时区')->maxLength(255)->dehydrateStateUsing(fn (?string $state): string => $state ?: 'Asia/Shanghai'),
                TextInput::make('store_currency')->label('货币')->maxLength(10)->dehydrateStateUsing(fn (?string $state): string => $state ?: 'CNY'),
                Textarea::make('welcome_message')->label('首页欢迎提示')->rows(2)->columnSpanFull(),
                TextInput::make('copyright_text')->label('页脚版权信息')->maxLength(500)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('品牌与模板')
                ->description('Logo、站点图标和前台模板。')
                ->schema([
                self::imagePathSelect('logo_path', 'Logo', '从媒体库选择图片；也可以点击加号直接上传新 Logo。'),
                self::imagePathSelect('favicon_path', '站点图标', '建议使用方形 PNG/SVG/ICO。'),
                Select::make('theme_template')->label('前台模板')->options([
                    'default' => '默认模板',
                ])->default('default')->dehydrateStateUsing(fn (?string $state): string => $state ?: 'default'),
            ])->columns(2)->columnSpanFull(),

            Section::make('颜色与布局')
                ->description('控制前台基础配色、按钮圆角和商品卡片密度。')
                ->schema([
                ColorPicker::make('primary_color')
                    ->label('主色')
                    ->default('#7CBFE2')
                    ->dehydrateStateUsing(fn (?string $state): string => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $state) ? (string) $state : '#7CBFE2'),
                ColorPicker::make('accent_color')
                    ->label('强调色')
                    ->default('#F2A8BE')
                    ->dehydrateStateUsing(fn (?string $state): string => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $state) ? (string) $state : '#F2A8BE'),
                ColorPicker::make('background_color')
                    ->label('背景色')
                    ->default('#FFF9FC')
                    ->dehydrateStateUsing(fn (?string $state): string => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $state) ? (string) $state : '#FFF9FC'),
                Select::make('button_radius')->label('按钮圆角')->options([
                    'none' => '直角',
                    'sm' => '小圆角',
                    'md' => '中圆角',
                ])->default('sm')->dehydrateStateUsing(fn (?string $state): string => $state ?: 'sm'),
                Select::make('product_card_density')->label('商品卡密度')->options([
                    'comfortable' => '舒适',
                    'compact' => '紧凑',
                ])->default('comfortable')->dehydrateStateUsing(fn (?string $state): string => $state ?: 'comfortable'),
            ])->columns(2)->columnSpanFull(),

            Section::make('页面背景')
                ->description('只控制页面背景图，不影响 Logo 或媒体库资源。')
                ->schema([
                    self::imagePathSelect('home_background_path', '首页背景图', '可选。设置后首页会使用该图作为页面背景。'),
                    self::imagePathSelect('auth_background_path', '登录/注册背景图', '可选。设置后登录和注册页使用该图作为背景。'),
                ])->columns(2)->columnSpanFull(),

            Section::make('订单隐私')
                ->description('超级管理员可控制前台用户默认能否看到订单号和物流号。')
                ->schema([
                Toggle::make('show_order_numbers_to_users')->label('默认向用户显示订单号')->default(false),
                Toggle::make('show_tracking_numbers_to_users')->label('默认向用户显示国内物流号')->default(true),
            ])->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)->columns(2)->columnSpanFull(),

            Section::make('预售与物流')
                ->description('预售商品未单独指定仓库时，使用这里的默认仓库进行履约分配；该设置不会为未配置邮费的预售商品自动收费。')
                ->schema([
                    Select::make('presale_default_warehouse_id')
                        ->label('预售默认仓库')
                        ->relationship('presaleDefaultWarehouse', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('可留空；留空时系统会按可用仓库顺序选择。'),
                ])->columns(2)->columnSpanFull(),

            Section::make('语言')
                ->description('前台语言默认跟随系统，并预留后续多语言扩展。')
                ->schema([
                Select::make('default_locale_mode')->label('默认语言模式')->options([
                    'system' => '跟随系统语言',
                    'zh_CN' => '中文',
                    'en' => 'English',
                    'ja' => '日本語',
                    'ko' => '한국어',
                    'fr' => 'Français',
                ])->default('system'),
                Select::make('enabled_locales')->label('启用语言')->multiple()->options([
                    'zh_CN' => '中文',
                    'en' => 'English',
                    'ja' => '日本語',
                    'ko' => '한국어',
                    'fr' => 'Français',
                ])->default(['zh_CN', 'en', 'ja', 'ko', 'fr']),
            ])->columns(2)->columnSpanFull(),

            Section::make('页面音乐')
                ->description('页面音乐是前台体验功能，只允许选择音频文件。')
                ->schema([
                Toggle::make('page_music_enabled')->label('启用页面音乐')->default(false),
                self::audioAssetPathSelect('page_music_asset_path', '页面音乐文件', '只允许引用或上传音频文件。'),
                Select::make('page_music_mode')->label('播放模式')->options([
                    'manual' => '手动播放',
                    'page' => '按页面配置',
                ])->default('manual'),
            ])->columns(2)->columnSpanFull(),

            Section::make('导购网页宠物')
                ->description('导购 AI 已迁移到后台 AI 菜单；此处只保留基础入口和素材配置。')
                ->schema([
                Toggle::make('guide_pet_enabled')->label('启用导购网页宠物')->default(false),
                self::assetPathSelect('guide_pet_asset_path', '导购宠物资源', '可上传宠物图片或动效资源，后续对接 AI 导购。'),
                Select::make('guide_pet_context_mode')->label('导购上下文')->options([
                    'storefront' => '前台页面',
                    'product' => '商品页',
                    'cart' => '购物车',
                ])->default('storefront'),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    private static function imagePathSelect(string $name, string $label, string $helperText): Select
    {
        return Select::make($name)
            ->label($label)
            ->helperText($helperText)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::imageOptions())
            ->getSearchResultsUsing(fn (string $search): array => self::imageOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->where('path', $value)->value('name') ?? $value)
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传图片')
                    ->helperText('上传图片，或填写下方外部图片 URL。')
                    ->disk('public_uploads')
                    ->directory('site')
                    ->image()
                    ->maxSize(5120),
                TextInput::make('external_url')
                    ->label('外部图片 URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('Alt 文案')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data) use ($name): string {
                $asset = MediaAsset::createImageFromUploadOrUrl(
                    $data,
                    str_contains($name, 'background') ? MediaAsset::USAGE_BACKGROUND : MediaAsset::USAGE_LOGO,
                );

                return $asset->path;
            });
    }

    private static function assetPathSelect(string $name, string $label, string $helperText): Select
    {
        return Select::make($name)
            ->label($label)
            ->helperText($helperText)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::assetOptions())
            ->getSearchResultsUsing(fn (string $search): array => self::assetOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->where('path', $value)->value('name') ?? $value)
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传文件')
                    ->disk('public_uploads')
                    ->directory('site')
                    ->maxSize(20480)
                    ->required(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('说明')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $path = is_array($data['path']) ? reset($data['path']) : $data['path'];

                $asset = MediaAsset::query()->create([
                    'name' => $data['name'] ?: pathinfo($path, PATHINFO_FILENAME),
                    'path' => $path,
                    'disk' => 'public_uploads',
                    'alt' => $data['alt'] ?? null,
                    'usage' => MediaAsset::USAGE_GENERAL,
                ]);

                return $asset->path;
            });
    }

    private static function audioAssetPathSelect(string $name, string $label, string $helperText): Select
    {
        return Select::make($name)
            ->label($label)
            ->helperText($helperText)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::audioOptions())
            ->getSearchResultsUsing(fn (string $search): array => self::audioOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => MediaAsset::query()->where('path', $value)->value('name') ?? $value)
            ->createOptionForm([
                FileUpload::make('path')
                    ->label('上传音频')
                    ->disk('public_uploads')
                    ->directory('site/audio')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/aac', 'audio/flac', 'audio/mp4'])
                    ->maxSize(20480)
                    ->required(),
                TextInput::make('name')->label('名称')->maxLength(255),
                TextInput::make('alt')->label('说明')->maxLength(255),
            ])
            ->createOptionUsing(function (array $data): string {
                $path = is_array($data['path']) ? reset($data['path']) : $data['path'];

                $asset = MediaAsset::query()->create([
                    'name' => $data['name'] ?: pathinfo($path, PATHINFO_FILENAME),
                    'path' => $path,
                    'disk' => 'public_uploads',
                    'alt' => $data['alt'] ?? null,
                    'usage' => MediaAsset::USAGE_GENERAL,
                ]);

                return $asset->path;
            });
    }

    private static function imageOptions(?string $search = null): array
    {
        return MediaAsset::query()
            ->where(function ($query): void {
                $query->where('mime_type', 'like', 'image/%')->orWhereNull('mime_type');
            })
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name ?: basename($asset->path)])
            ->all();
    }

    private static function assetOptions(?string $search = null): array
    {
        return MediaAsset::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name ?: basename($asset->path)])
            ->all();
    }

    private static function audioOptions(?string $search = null): array
    {
        return MediaAsset::query()
            ->where('mime_type', 'like', 'audio/%')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->path => $asset->name ?: basename($asset->path)])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_name')->label('站点名称'),
                TextColumn::make('updated_at')->label('更新')->dateTime(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteSettings::route('/'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
