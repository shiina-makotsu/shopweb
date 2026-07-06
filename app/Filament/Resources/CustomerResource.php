<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\UserProfileChangeLog;
use App\Services\CouponService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;

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
            ->withCount(['orders as completed_orders_count' => fn (Builder $query): Builder => $query
                ->where('status', Order::STATUS_FULFILLED)])
            ->with(['inviter'])
            ->withCount('referrals')
            ->withSum(['orders as completed_orders_total_cents' => fn (Builder $query): Builder => $query
                ->where('status', Order::STATUS_FULFILLED)], 'total_cents');
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
                TextInput::make('name')->label('用户昵称')->required()->maxLength(255),
                TextInput::make('email')->label('注册邮箱')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                FileUpload::make('avatar_path')
                    ->label('头像')
                    ->disk('public_uploads')
                    ->directory('avatars')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->imageCropAspectRatio('1:1')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->maxSize(5120)
                    ->openable()
                    ->downloadable(),
                Textarea::make('profile_intro')
                    ->label('个人简介')
                    ->rows(4)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                DatePicker::make('birthday')
                    ->label('生日')
                    ->maxDate(now())
                    ->native(false),
                Toggle::make('has_diagnosis_certificate')
                    ->label('持有诊断证明')
                    ->default(false),
                Select::make('account_type')->label('用户身份')->options([
                    'regular' => '普通用户',
                    'member' => '会员用户（占位）',
                ])->default('regular')->required(),
                Select::make('forum_role')->label('论坛身份')->options([
                    'member' => '普通用户',
                    'moderator' => '版主',
                ])->default('member')->required(),
                DateTimePicker::make('forum_posting_banned_at')
                    ->label('发帖封禁时间')
                    ->helperText('留空表示可发帖；有值表示禁止发帖。'),
                TextInput::make('forum_posting_ban_reason')->label('发帖封禁原因')->maxLength(255),
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
            Section::make('邀请关系')->schema([
                Placeholder::make('referral_code')->label('邀请码')->content(fn (?User $record): string => $record?->referral_code ?: '-'),
                Placeholder::make('inviter')->label('被谁邀请')->content(fn (?User $record): string => $record?->inviter ? static::userLabel($record->inviter) : '-'),
                Placeholder::make('referrals_count')->label('邀请人数')->content(fn (?User $record): string => (string) ($record?->referrals()->count() ?? 0)),
            ])->columns(3)->columnSpanFull(),
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
                    ->label('用户昵称')
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
                TextColumn::make('birthday')
                    ->label('生日')
                    ->formatStateUsing(fn ($state, User $record): string => $state ? ($record->hasBirthdayToday() ? '生日 '.$record->birthday?->format('Y-m-d') : $record->birthday?->format('Y-m-d')) : '-')
                    ->badge(fn (User $record): bool => $record->hasBirthdayToday())
                    ->color(fn (User $record): string => $record->hasBirthdayToday() ? 'success' : 'gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('has_diagnosis_certificate')
                    ->label('诊断证明')
                    ->formatStateUsing(fn (bool $state): string => $state ? '已持有' : '未标记')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),
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
                TextColumn::make('forum_posting_banned_at')
                    ->label('发帖权限')
                    ->formatStateUsing(fn ($state): string => $state ? '已封禁' : '可发帖')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('completed_orders_count')->label('完成订单数')->sortable(),
                TextColumn::make('inviter.name')
                    ->label('邀请人')
                    ->formatStateUsing(fn ($state, User $record): string => $record->inviter ? static::userLabel($record->inviter) : '-')
                    ->toggleable(),
                TextColumn::make('referrals_count')
                    ->label('邀请人数')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ((int) $state).' 人')
                    ->sortable(),
                TextColumn::make('completed_orders_total_cents')
                    ->label('完成累计金额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) ($state ?? 0)))
                    ->sortable(),
                TextColumn::make('can_view_order_numbers')->label('订单号可见')->formatStateUsing(fn (bool $state): string => $state ? '可见' : '隐藏')->badge(),
                TextColumn::make('can_view_tracking_numbers')->label('国际物流号')->formatStateUsing(fn (bool $state): string => $state ? '可见' : '隐藏')->badge(),
                TextColumn::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('issueCoupon')
                    ->label('发放优惠码')
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(fn (): bool => AdminAccess::canAction('coupons.issue'))
                    ->form([
                        Select::make('coupon_id')
                            ->label('优惠码')
                            ->options(fn (): array => \App\Filament\Resources\CouponResource::couponOptions())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('note')->label('备注')->maxLength(255),
                    ])
                    ->action(function (User $record, array $data): void {
                        $coupon = Coupon::query()->findOrFail($data['coupon_id']);

                        app(CouponService::class)->issueToUser(
                            $coupon,
                            $record,
                            UserCoupon::SOURCE_ADMIN,
                            auth()->user(),
                            null,
                            $data['note'] ?? null,
                        );
                    }),
                Action::make('viewReferrals')
                    ->label('查看邀请')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->modalHeading(fn (User $record): string => '邀请明细：'.static::userLabel($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalWidth('4xl')
                    ->modalContent(fn (User $record): HtmlString => static::referralsHtml($record)),
                EditAction::make()
                    ->url(fn (User $record): string => static::getUrl('edit', ['record' => $record->getKey()])),
            ])
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function recordProfileChanges(User $record, array $data, ?User $changedBy = null): void
    {
        foreach (['birthday', 'has_diagnosis_certificate'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = static::profileLogValue($record->{$field});
            $new = static::profileLogValue($data[$field]);

            if ($old === $new) {
                continue;
            }

            UserProfileChangeLog::query()->create([
                'user_id' => $record->id,
                'changed_by_id' => $changedBy?->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'source' => 'admin',
            ]);
        }
    }

    private static function profileLogValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function userLabel(User $user): string
    {
        return $user->displayName().' / '.$user->public_id.' / '.$user->email;
    }

    private static function referralsHtml(User $user): HtmlString
    {
        $referrals = $user->referrals()->latest()->get();

        if ($referrals->isEmpty()) {
            return new HtmlString('<p style="margin:0;color:#64748b;">该用户暂未邀请其他用户。</p>');
        }

        $rows = $referrals->map(function (User $referral): string {
            $label = e(static::userLabel($referral));
            $registeredAt = e($referral->created_at?->format('Y-m-d H:i') ?? '-');

            return <<<HTML
                <tr>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;">{$label}</td>
                    <td style="padding:10px 12px;border-top:1px solid #e2e8f0;white-space:nowrap;">{$registeredAt}</td>
                </tr>
            HTML;
        })->implode('');

        return new HtmlString(<<<HTML
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.5;">
                    <thead>
                        <tr style="background:#f8fafc;color:#334155;">
                            <th style="padding:10px 12px;text-align:left;">被邀请用户</th>
                            <th style="padding:10px 12px;text-align:left;">注册时间</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        HTML);
    }
}
