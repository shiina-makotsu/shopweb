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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
                TextColumn::make('avatar_path')
                    ->label('头像')
                    ->state(fn (User $record): HtmlString => static::avatarHtml($record))
                    ->html(),
                TextColumn::make('name')
                    ->label('用户昵称')
                    ->state(fn (User $record): HtmlString => static::rowTriggerHtml($record))
                    ->html()
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['name'], $search))
                    ->sortable(),
                TextColumn::make('public_id')
                    ->label('用户 ID')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['public_id'], $search))
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('用户身份')
                    ->formatStateUsing(fn (?string $state): string => $state === 'member' ? '会员用户' : '普通用户')
                    ->badge()
                    ->sortable(),
                TextColumn::make('referrals_count')
                    ->label('邀请人数')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ((int) $state).' 人')
                    ->sortable(),
                TextColumn::make('completed_orders_total_cents')
                    ->label('完成累计金额')
                    ->formatStateUsing(fn ($state): string => Money::format((int) ($state ?? 0)))
                    ->sortable(),
                TextColumn::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
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

    private static function avatarHtml(User $user): HtmlString
    {
        if ($user->avatar_path) {
            $url = e(Storage::disk('public_uploads')->url($user->avatar_path));
            $alt = e($user->displayName());

            return new HtmlString(<<<HTML
                <span style="display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;overflow:hidden;border-radius:999px;border:1px solid #cbd5e1;background:#fff;">
                    <img src="{$url}" alt="{$alt}" style="width:100%;height:100%;object-fit:cover;">
                </span>
            HTML);
        }

        return new HtmlString(<<<HTML
            <span style="display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc;color:#64748b;font-size:22px;">
                <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
            </span>
        HTML);
    }

    private static function userLabel(User $user): string
    {
        return $user->displayName().' / '.$user->public_id.' / '.$user->email;
    }

    private static function rowTriggerHtml(User $record): HtmlString
    {
        $label = e($record->name ?: $record->public_id);
        $details = static::rowDetailsHtml($record)->toHtml();

        return new HtmlString(<<<HTML
            <span data-shopweb-customer-trigger style="display:block;font-weight:600;color:#0f172a;">{$label}</span>
            <template data-shopweb-customer-template>{$details}</template>
            <script>
                if (! window.shopwebCustomerRowToggleBound) {
                    window.shopwebCustomerRowToggleBound = true;
                    document.addEventListener('click', function (event) {
                        if (event.target.closest('a,button,input,select,textarea,label,[role="button"],[data-shopweb-customer-row-form]')) {
                            return;
                        }

                        var trigger = event.target.closest('[data-shopweb-customer-trigger]');
                        var row = trigger ? trigger.closest('tr') : event.target.closest('tr');
                        if (! row || ! row.querySelector('[data-shopweb-customer-template]')) {
                            return;
                        }

                        var next = row.nextElementSibling;
                        if (next && next.dataset.shopwebCustomerExpanded === 'true') {
                            next.remove();
                            row.classList.remove('shopweb-customer-row-open');
                            return;
                        }

                        document.querySelectorAll('tr[data-shopweb-customer-expanded="true"]').forEach(function (item) {
                            item.previousElementSibling && item.previousElementSibling.classList.remove('shopweb-customer-row-open');
                            item.remove();
                        });

                        var template = row.querySelector('[data-shopweb-customer-template]');
                        var expanded = document.createElement('tr');
                        expanded.dataset.shopwebCustomerExpanded = 'true';
                        var cell = document.createElement('td');
                        cell.colSpan = row.children.length;
                        cell.style.padding = '0';
                        cell.innerHTML = template.innerHTML;
                        expanded.appendChild(cell);
                        row.insertAdjacentElement('afterend', expanded);
                        row.classList.add('shopweb-customer-row-open');
                    });
                }
            </script>
        HTML);
    }

    private static function rowDetailsHtml(User $record): HtmlString
    {
        $record->loadMissing('inviter');

        $email = e($record->email ?: '');
        $emailLabel = e($record->email ?: '-');
        $birthday = e($record->birthday?->format('Y-m-d') ?: '');
        $birthdayLabel = e($record->birthday?->format('Y-m-d') ?: '-');
        $diagnosisLabel = e($record->has_diagnosis_certificate ? '已持有' : '未标记');
        $diagnosisChecked = $record->has_diagnosis_certificate ? ' checked' : '';
        $forumRoleLabel = e($record->forum_role === 'moderator' ? '版主' : '普通用户');
        $regularSelected = $record->account_type === 'member' ? '' : ' selected';
        $memberSelected = $record->account_type === 'member' ? ' selected' : '';
        $forumMemberSelected = $record->forum_role === 'moderator' ? '' : ' selected';
        $forumModeratorSelected = $record->forum_role === 'moderator' ? ' selected' : '';
        $bannedAt = e($record->forum_posting_banned_at?->format('Y-m-d\TH:i') ?: '');
        $bannedAtLabel = e($record->forum_posting_banned_at?->format('Y-m-d H:i') ?: '可发帖');
        $banReason = e($record->forum_posting_ban_reason ?: '');
        $banReasonLabel = e($record->forum_posting_ban_reason ?: '-');
        $orderVisibleChecked = $record->can_view_order_numbers ? ' checked' : '';
        $trackingVisibleChecked = $record->can_view_tracking_numbers ? ' checked' : '';
        $completedOrders = e((string) ((int) ($record->completed_orders_count ?? $record->orders()->where('status', Order::STATUS_FULFILLED)->count())));
        $completedTotal = e(Money::format((int) ($record->completed_orders_total_cents ?? 0)));
        $orderVisibleLabel = e($record->can_view_order_numbers ? '可见' : '隐藏');
        $trackingVisibleLabel = e($record->can_view_tracking_numbers ? '可见' : '隐藏');
        $inviter = e($record->inviter ? static::userLabel($record->inviter) : '-');
        $referralsCount = e((string) ((int) ($record->referrals_count ?? $record->referrals()->count())));
        $referrals = $record->referrals()->latest()->limit(20)->get();
        $referralsList = $referrals->isEmpty()
            ? '<span style="color:#64748b;">暂无邀请用户</span>'
            : $referrals->map(fn (User $referral): string => '<span style="display:inline-flex;border:1px solid #cbd5e1;border-radius:999px;background:#fff;padding:2px 8px;margin:2px 4px 2px 0;">'.e(static::userLabel($referral)).'</span>')->implode('');
        $action = e(route('admin.customers.quick-update', $record, absolute: false));
        $csrf = e(csrf_token());
        $superAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $privacyControls = $superAdmin
            ? <<<HTML
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="can_view_order_numbers" value="1"{$orderVisibleChecked}> 订单号可见
                </label>
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="can_view_tracking_numbers" value="1"{$trackingVisibleChecked}> 国际物流号可见
                </label>
            HTML
            : '<p style="margin:0;color:#64748b;">订单号/国际物流号可见性仅超级管理员可修改。</p>';

        return new HtmlString(<<<HTML
            <div class="shopweb-customer-submenu" style="padding:14px 18px 14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;border-left:3px solid #94a3b8;color:#0f172a;font-size:13px;line-height:1.6;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                    <div style="display:grid;grid-template-columns:92px minmax(0,1fr);gap:6px 10px;">
                        <strong style="color:#475569;">注册邮箱</strong><div style="word-break:break-all;">{$emailLabel}</div>
                        <strong style="color:#475569;">生日</strong><div>{$birthdayLabel}</div>
                        <strong style="color:#475569;">诊断证明</strong><div>{$diagnosisLabel}</div>
                        <strong style="color:#475569;">论坛身份</strong><div>{$forumRoleLabel}</div>
                        <strong style="color:#475569;">发帖权限</strong><div>{$bannedAtLabel}</div>
                        <strong style="color:#475569;">封禁原因</strong><div style="word-break:break-word;">{$banReasonLabel}</div>
                    </div>
                    <div style="display:grid;grid-template-columns:112px minmax(0,1fr);gap:6px 10px;">
                        <strong style="color:#475569;">完成订单数</strong><div>{$completedOrders}</div>
                        <strong style="color:#475569;">完成累计金额</strong><div>{$completedTotal}</div>
                        <strong style="color:#475569;">订单号可见</strong><div>{$orderVisibleLabel}</div>
                        <strong style="color:#475569;">国际物流号</strong><div>{$trackingVisibleLabel}</div>
                        <strong style="color:#475569;">邀请人</strong><div style="word-break:break-word;">{$inviter}</div>
                        <strong style="color:#475569;">邀请人数</strong><div>{$referralsCount} 人</div>
                    </div>
                    <div>
                        <strong style="display:block;color:#475569;margin-bottom:6px;">邀请明细</strong>
                        <div style="max-height:120px;overflow:auto;">{$referralsList}</div>
                    </div>
                </div>
                <form method="POST" action="{$action}" data-shopweb-customer-row-form style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 12px;align-items:end;" onclick="event.stopPropagation();">
                    <input type="hidden" name="_token" value="{$csrf}">
                    <label>注册邮箱<input name="email" type="email" value="{$email}" required style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"></label>
                    <label>生日<input name="birthday" type="date" value="{$birthday}" style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"></label>
                    <label>用户身份<select name="account_type" style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"><option value="regular"{$regularSelected}>普通用户</option><option value="member"{$memberSelected}>会员用户</option></select></label>
                    <label>论坛身份<select name="forum_role" style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"><option value="member"{$forumMemberSelected}>普通用户</option><option value="moderator"{$forumModeratorSelected}>版主</option></select></label>
                    <label>发帖封禁时间<input name="forum_posting_banned_at" type="datetime-local" value="{$bannedAt}" style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"></label>
                    <label>封禁原因<input name="forum_posting_ban_reason" value="{$banReason}" style="margin-top:4px;width:100%;min-height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:4px 8px;color:#0f172a;"></label>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="has_diagnosis_certificate" value="1"{$diagnosisChecked}> 持有诊断证明</label>
                        {$privacyControls}
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" style="border:1px solid #94a3b8;border-radius:8px;background:#fff;padding:7px 14px;color:#0f172a;cursor:pointer;">保存快速详情</button>
                    </div>
                </form>
            </div>
        HTML);
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
