<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\FlashSaleCampaignResource\Pages\CreateFlashSaleCampaign;
use App\Filament\Resources\FlashSaleCampaignResource\Pages\EditFlashSaleCampaign;
use App\Filament\Resources\FlashSaleCampaignResource\Pages\ListFlashSaleCampaigns;
use App\Models\FlashSaleCampaign;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\MoneyInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlashSaleCampaignResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = FlashSaleCampaign::class;

    protected static string $permissionArea = 'coupons';

    protected static ?string $navigationLabel = '秒杀计划';

    protected static ?string $modelLabel = '秒杀计划';

    protected static ?string $pluralModelLabel = '秒杀计划';

    protected static string|\UnitEnum|null $navigationGroup = '交易';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('活动规则')
                ->schema([
                    TextInput::make('name')->label('计划名称')->required()->maxLength(255),
                    Select::make('schedule_type')
                        ->label('秒杀类型')
                        ->options(FlashSaleCampaign::scheduleTypeOptions())
                        ->default(FlashSaleCampaign::TYPE_ONCE)
                        ->required()
                        ->live(),
                    DatePicker::make('starts_on')
                        ->label(fn (Get $get): string => $get('schedule_type') === FlashSaleCampaign::TYPE_ONCE ? '秒杀日期' : '规则开始日期')
                        ->required()
                        ->native(false),
                    DatePicker::make('ends_on')
                        ->label('规则结束日期')
                        ->helperText('周期秒杀留空表示持续生成；一次秒杀可留空。')
                        ->native(false),
                    TimePicker::make('starts_at_time')
                        ->label('开始时间')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('ends_at_time')
                        ->label('结束时间')
                        ->seconds(false),
                    Select::make('week_days')
                        ->label('每周哪几天')
                        ->multiple()
                        ->options([
                            1 => '周一',
                            2 => '周二',
                            3 => '周三',
                            4 => '周四',
                            5 => '周五',
                            6 => '周六',
                            7 => '周日',
                        ])
                        ->visible(fn (Get $get): bool => $get('schedule_type') === FlashSaleCampaign::TYPE_WEEKLY),
                    Select::make('month_days')
                        ->label('每月哪几天')
                        ->multiple()
                        ->options(array_combine(range(1, 31), range(1, 31)))
                        ->visible(fn (Get $get): bool => $get('schedule_type') === FlashSaleCampaign::TYPE_MONTHLY),
                    Select::make('year_dates')
                        ->label('每年哪几天')
                        ->multiple()
                        ->options(self::yearDateOptions())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('schedule_type') === FlashSaleCampaign::TYPE_YEARLY)
                        ->helperText('格式为月-日，例如 06-18。'),
                    TextInput::make('generate_days_ahead')
                        ->label('提前生成天数')
                        ->numeric()
                        ->default(60)
                        ->minValue(1)
                        ->maxValue(366)
                        ->helperText('系统每天自动生成未来范围内的具体秒杀场次。'),
                    Toggle::make('is_active')->label('启用')->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('参与商品')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->label('商品与价格')
                        ->schema([
                            Select::make('product_id')
                                ->label('商品')
                                ->options(fn (): array => Product::query()
                                    ->whereIn('status', [Product::STATUS_PUBLISHED, Product::STATUS_PRESALE])
                                    ->orderBy('title')
                                    ->limit(200)
                                    ->pluck('title', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('product_variant_ids')
                                ->label('允许规格')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText('留空表示该商品所有启用规格都可在秒杀结算页选择。')
                                ->options(fn (Get $get): array => ProductVariant::query()
                                    ->where('product_id', $get('product_id'))
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->sku.' / '.$variant->specLabel().' / 库存 '.$variant->stock])
                                    ->all()),
                            ...MoneyInput::convertedCents(TextInput::make('sale_price_cents')->label('秒杀价')->required()->minValue(1)),
                            TextInput::make('quantity_limit')
                                ->label('每场名额')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                            Toggle::make('is_active')->label('参与')->default(true),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('添加参与商品')
                        ->required(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('计划')->searchable(),
                TextColumn::make('schedule_type')
                    ->label('类型')
                    ->formatStateUsing(fn (?string $state): string => FlashSaleCampaign::scheduleTypeOptions()[$state] ?? (string) $state),
                TextColumn::make('items_count')->counts('items')->label('商品数'),
                TextColumn::make('starts_on')->label('开始日期')->date('Y-m-d')->sortable(),
                TextColumn::make('starts_at_time')->label('开始时间'),
                TextColumn::make('ends_at_time')->label('结束时间'),
                TextColumn::make('last_generated_at')->label('最近生成')->dateTime('Y-m-d H:i')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSaleCampaigns::route('/'),
            'create' => CreateFlashSaleCampaign::route('/create'),
            'edit' => EditFlashSaleCampaign::route('/{record}/edit'),
        ];
    }

    private static function yearDateOptions(): array
    {
        $options = [];

        for ($month = 1; $month <= 12; $month++) {
            $days = cal_days_in_month(CAL_GREGORIAN, $month, 2024);

            for ($day = 1; $day <= $days; $day++) {
                $key = sprintf('%02d-%02d', $month, $day);
                $options[$key] = $key;
            }
        }

        return $options;
    }
}
