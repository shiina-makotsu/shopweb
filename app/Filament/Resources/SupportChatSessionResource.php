<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\SupportChatSessionResource\Pages\EditSupportChatSession;
use App\Filament\Resources\SupportChatSessionResource\Pages\ListSupportChatSessions;
use App\Models\Coupon;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Services\BackofficeApprovalService;
use App\Services\SupportChatService;
use App\Support\AdminAccess;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportChatSessionResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = SupportChatSession::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '客服会话';
    protected static ?string $modelLabel = '客服会话';
    protected static ?string $pluralModelLabel = '客服会话';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can(static::$permissionArea)
            || AdminAccess::canAction('coupons.issue')
            || AdminAccess::canAction('after_sales.refund')
            || AdminAccess::canAction('approvals.review');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('user_id')
            ->whereHas('messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::unreadCustomerMessageQuery()->count();

        return $count > 0 ? ($count > 99 ? '99+' : (string) $count) : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('会话信息')->schema([
                Placeholder::make('customer')->label('客户')->content(fn (?SupportChatSession $record): string => $record?->user?->displayName() ?? $record?->guest_id ?? '-'),
                Placeholder::make('assigned_admin')->label('当前客服')->content(fn (?SupportChatSession $record): string => $record?->assignedAdmin?->displayName() ?? '尚未接入'),
                Placeholder::make('order_number')->label('关联订单')->content(fn (?SupportChatSession $record): string => $record?->order?->order_number ?? '-'),
                Placeholder::make('status_label')->label('状态')->content(fn (?SupportChatSession $record): string => static::statusLabel($record?->status)),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('会话')->formatStateUsing(fn ($state): string => '#'.$state)->sortable(),
                TextColumn::make('user.name')
                    ->label('客户')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $userQuery) => RegexSearch::where($userQuery, ['name', 'email', 'public_id'], $search))),
                TextColumn::make('order.order_number')->label('订单号')->searchable()->toggleable(),
                TextColumn::make('assignedAdmin.name')->label('客服')->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->badge(),
                TextColumn::make('last_message_at')->label('最后消息')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('served_count')->label('完成接待')->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->toolbarActions([
                Action::make('startCustomerChat')
                    ->label('主动发起客服消息')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (): bool => AdminAccess::can('support'))
                    ->form([
                        Select::make('user_id')
                            ->label('前台用户')
                            ->options(fn (): array => User::query()
                                ->where('role', AdminAccess::ROLE_CUSTOMER)
                                ->orderBy('name')
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => $user->displayName().' / '.$user->public_id.' / '.$user->email])
                                ->all())
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->where('role', AdminAccess::ROLE_CUSTOMER)
                                ->where(function (Builder $query) use ($search): void {
                                    RegexSearch::where($query, ['name', 'public_id', 'email'], $search);
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => $user->displayName().' / '.$user->public_id.' / '.$user->email])
                                ->all())
                            ->required(),
                        Textarea::make('message')
                            ->label('消息内容')
                            ->rows(5)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $customer = User::query()
                            ->where('role', AdminAccess::ROLE_CUSTOMER)
                            ->findOrFail($data['user_id']);

                        app(SupportChatService::class)->startForUser($customer, auth()->user(), $data['message']);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                ...static::approvalActions(),
                Action::make('assign')
                    ->label('接待')
                    ->color('info')
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed() && ($record->assigned_admin_id !== auth()->id() || $record->status !== SupportChatSession::STATUS_ACTIVE))
                    ->action(fn (SupportChatSession $record) => app(SupportChatService::class)->assign($record, auth()->user())),
                Action::make('reply')
                    ->label('回复')
                    ->form([
                        Textarea::make('message')->label('回复内容')->required()->rows(4),
                    ])
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed())
                    ->action(fn (SupportChatSession $record, array $data) => app(SupportChatService::class)->reply($record, auth()->user(), $data['message'])),
                Action::make('end')
                    ->label('结束接待')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SupportChatSession $record): bool => ! $record->isClosed() && $record->status !== SupportChatSession::STATUS_ENDED)
                    ->action(fn (SupportChatSession $record) => app(SupportChatService::class)->end($record, auth()->user())),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    public static function approvalActions(?SupportChatSession $fixedRecord = null): array
    {
        return [
            Action::make('requestCoupon')
                ->label('申请优惠码')
                ->icon(Heroicon::OutlinedTicket)
                ->color('warning')
                ->visible(fn (?SupportChatSession $record = null): bool => static::resolveActionRecord($record, $fixedRecord)?->user_id
                    && ! AdminAccess::canAction('coupons.issue')
                    && AdminAccess::canAction('coupons.issue_request'))
                ->form([
                    Select::make('coupon_id')
                        ->label('优惠码')
                        ->options(fn (): array => CouponResource::couponOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('note')->label('申请说明')->rows(4)->required(),
                ])
                ->action(function (array $data, ?SupportChatSession $record = null) use ($fixedRecord): void {
                    $record = static::resolveActionRecord($record, $fixedRecord);
                    $coupon = Coupon::query()->findOrFail($data['coupon_id']);

                    app(BackofficeApprovalService::class)->requestCouponFromChat(
                        $record,
                        $coupon,
                        auth()->user(),
                        $data['note'] ?? null,
                    );
                }),
            Action::make('issueCoupon')
                ->label('发放优惠码')
                ->icon(Heroicon::OutlinedTicket)
                ->color('success')
                ->visible(fn (?SupportChatSession $record = null): bool => (bool) static::resolveActionRecord($record, $fixedRecord)?->user_id && AdminAccess::canAction('coupons.issue'))
                ->form([
                    Select::make('coupon_id')
                        ->label('优惠码')
                        ->options(fn (): array => CouponResource::couponOptions())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('note')->label('发放说明')->rows(4),
                ])
                ->action(function (array $data, ?SupportChatSession $record = null) use ($fixedRecord): void {
                    $record = static::resolveActionRecord($record, $fixedRecord);
                    $coupon = Coupon::query()->findOrFail($data['coupon_id']);

                    app(BackofficeApprovalService::class)->issueCouponForChat(
                        $record,
                        $coupon,
                        auth()->user(),
                        $data['note'] ?? null,
                    );
                }),
            Action::make('requestRefund')
                ->label('申请退款')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('warning')
                ->visible(fn (?SupportChatSession $record = null): bool => (bool) static::resolveActionRecord($record, $fixedRecord)?->user_id
                    && (bool) static::resolveActionRecord($record, $fixedRecord)?->order_id
                    && ! AdminAccess::canAction('after_sales.refund')
                    && AdminAccess::canAction('after_sales.request_refund'))
                ->form([
                    ...MoneyInput::convertedCents(TextInput::make('refund_amount_cents')->label('退款金额')->required()),
                    Textarea::make('note')->label('申请说明')->rows(4)->required(),
                ])
                ->action(fn (array $data, ?SupportChatSession $record = null) => app(BackofficeApprovalService::class)->requestRefundFromChat(
                    static::resolveActionRecord($record, $fixedRecord),
                    auth()->user(),
                    (int) $data['refund_amount_cents'],
                    $data['note'] ?? null,
                )),
            Action::make('approveRefund')
                ->label('直接退款')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (?SupportChatSession $record = null): bool => (bool) static::resolveActionRecord($record, $fixedRecord)?->user_id
                    && (bool) static::resolveActionRecord($record, $fixedRecord)?->order_id
                    && AdminAccess::canAction('after_sales.refund'))
                ->form([
                    ...MoneyInput::convertedCents(TextInput::make('refund_amount_cents')->label('退款金额')->required()),
                    Textarea::make('note')->label('退款说明')->rows(4),
                ])
                ->action(fn (array $data, ?SupportChatSession $record = null) => app(BackofficeApprovalService::class)->approveRefundForChat(
                    static::resolveActionRecord($record, $fixedRecord),
                    auth()->user(),
                    (int) $data['refund_amount_cents'],
                    $data['note'] ?? null,
                )),
        ];
    }

    private static function resolveActionRecord(?SupportChatSession $record, ?SupportChatSession $fixedRecord): SupportChatSession
    {
        return $fixedRecord ?? $record ?? throw new \RuntimeException('Support chat session record is required.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportChatSessions::route('/'),
            'edit' => EditSupportChatSession::route('/{record}/edit'),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            SupportChatSession::STATUS_ACTIVE => '接待中',
            SupportChatSession::STATUS_ENDED => '已结束',
            SupportChatSession::STATUS_CLOSED => '用户已关闭',
            default => '等待接入',
        };
    }

    protected static function pendingReceptionQuery(): Builder
    {
        return SupportChatSession::query()
            ->whereNotNull('user_id')
            ->where('status', SupportChatSession::STATUS_OPEN)
            ->whereNull('assigned_admin_id')
            ->whereHas('messages', fn (Builder $query): Builder => $query->whereIn('sender_type', ['customer', 'guest']));
    }

    protected static function unreadCustomerMessageQuery(): Builder
    {
        return SupportChatSession::query()
            ->whereNotNull('user_id')
            ->whereNotIn('status', [SupportChatSession::STATUS_ENDED, SupportChatSession::STATUS_CLOSED])
            ->whereHas('messages', fn (Builder $query): Builder => $query
                ->where('sender_type', SupportChatMessage::SENDER_CUSTOMER)
                ->whereNull('read_at'));
    }
}
