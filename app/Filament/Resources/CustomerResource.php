<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\User;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CustomerResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = User::class;
    protected static string $permissionArea = 'customers';
    protected static ?string $navigationLabel = '前台用户';
    protected static ?string $modelLabel = '前台用户';
    protected static ?string $pluralModelLabel = '前台用户';
    protected static string|\UnitEnum|null $navigationGroup = '用户';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'customer')
            ->withCount('orders')
            ->withSum('orders as orders_total_cents', 'total_cents');
    }

    public static function resolveRecordRouteBinding(int|string $key, ?\Closure $modifyQuery = null): ?Model
    {
        $record = parent::resolveRecordRouteBinding($key, $modifyQuery);

        if ($record || ! ctype_digit((string) $key)) {
            return $record;
        }

        $query = static::getRecordRouteBindingEloquentQuery();

        if ($modifyQuery) {
            $query = $modifyQuery($query) ?? $query;
        }

        return $query->whereKey((int) $key)->first();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('前台用户信息')->schema([
                Hidden::make('role')->default('customer'),
                TextInput::make('public_id')
                    ->label('用户 ID')
                    ->required()
                    ->regex('/^[A-Za-z0-9_]+$/')
                    ->notRegex('/^staff_/i')
                    ->unique(ignoreRecord: true)
                    ->maxLength(40)
                    ->helperText('只能使用英文、数字、下划线；不能和其他用户重复；staff_ 前缀保留给后台用户。'),
                TextInput::make('name')->label('用户名')->required()->maxLength(255),
                TextInput::make('nickname')->label('昵称')->maxLength(255),
                TextInput::make('email')->label('注册邮箱')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                FileUpload::make('avatar_path')
                    ->label('头像')
                    ->disk('public_uploads')
                    ->directory('avatars')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->maxSize(5120)
                    ->openable()
                    ->downloadable(),
                Textarea::make('profile_intro')
                    ->label('个人简介')
                    ->rows(4)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Select::make('account_type')->label('用户身份')->options([
                    'regular' => '普通用户',
                    'member' => '会员用户（占位）',
                ])->default('regular')->required(),
                Select::make('forum_role')->label('论坛身份')->options([
                    'member' => '普通用户',
                    'moderator' => '版主',
                ])->default('member')->required(),
                Select::make('preferred_locale')->label('语言偏好')->options([
                    'system' => '跟随系统',
                    'zh_CN' => '中文',
                    'en' => '英语',
                    'ja' => '日语',
                    'ko' => '韩语',
                    'fr' => '法语',
                ])->default('system'),
                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
            ])->columns(2)->columnSpanFull(),
            Section::make('订单隐私')->schema([
                Toggle::make('can_view_order_numbers')->label('允许查看订单号')->helperText('关闭则显示订单内部编号。'),
                Toggle::make('can_view_tracking_numbers')->label('允许查看国际物流号')->helperText('国内物流默认可见；这里用于开放进货中/国际物流单号。'),
            ])->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('头像')
                    ->disk('public_uploads')
                    ->imageSize(40),
                TextColumn::make('name')
                    ->label('用户名')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name'], $search))
                    ->sortable(),
                TextColumn::make('public_id')
                    ->label('用户 ID')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['public_id'], $search))
                    ->sortable(),
                TextColumn::make('email')
                    ->label('注册邮箱')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['email'], $search))
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('用户身份')
                    ->formatStateUsing(fn (?string $state): string => $state === 'member' ? '会员用户' : '普通用户')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('forum_role')
                    ->label('论坛身份')
                    ->formatStateUsing(fn (?string $state): string => $state === 'moderator' ? '版主' : '普通用户')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('orders_count')->label('订单数')->sortable(),
                TextColumn::make('orders_total_cents')
                    ->label('累计金额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) ($state ?? 0)))
                    ->sortable(),
                TextColumn::make('can_view_order_numbers')->label('订单号可见')->formatStateUsing(fn (bool $state): string => $state ? '可见' : '隐藏')->badge(),
                TextColumn::make('can_view_tracking_numbers')->label('国际物流号')->formatStateUsing(fn (bool $state): string => $state ? '可见' : '隐藏')->badge(),
                TextColumn::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkAction::make('showOrderNumbers')
                    ->label('批量显示订单号')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['can_view_order_numbers' => true])),
                BulkAction::make('hideOrderNumbers')
                    ->label('批量隐藏订单号')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['can_view_order_numbers' => false])),
                BulkAction::make('showTrackingNumbers')
                    ->label('批量显示国际物流号')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['can_view_tracking_numbers' => true])),
                BulkAction::make('hideTrackingNumbers')
                    ->label('批量隐藏国际物流号')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['can_view_tracking_numbers' => false])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
