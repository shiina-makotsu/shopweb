<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\ApprovalRequestResource\Pages\EditApprovalRequest;
use App\Filament\Resources\ApprovalRequestResource\Pages\ListApprovalRequests;
use App\Models\AfterSalesRequest;
use App\Services\BackofficeApprovalService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequestResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = AfterSalesRequest::class;
    protected static string $permissionArea = 'approvals';
    protected static ?string $navigationLabel = '审批';
    protected static ?string $modelLabel = '审批';
    protected static ?string $pluralModelLabel = '审批';
    protected static string|\UnitEnum|null $navigationGroup = '审批';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can(static::$permissionArea)
            || AdminAccess::canAction('approvals.review')
            || AdminAccess::canAction('after_sales.refund')
            || AdminAccess::canAction('coupons.issue');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $refundQuery): void {
                        $refundQuery
                            ->where('resolution_type', AfterSalesRequest::RESOLUTION_REFUND)
                            ->where('refund_status', AfterSalesRequest::REFUND_REQUESTED);
                    })
                    ->orWhere(function (Builder $couponQuery): void {
                        $couponQuery
                            ->where('resolution_type', AfterSalesRequest::RESOLUTION_COUPON)
                            ->whereNotNull('coupon_id')
                            ->whereNotIn('status', [AfterSalesRequest::STATUS_RESOLVED, AfterSalesRequest::STATUS_CLOSED]);
                    });
            })
            ->with(['user', 'order', 'coupon', 'refundRequester']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return AfterSalesRequestResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label('审批事项')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['subject', 'message', 'admin_note'], $search))
                    ->sortable(),
                TextColumn::make('resolution_type')
                    ->label('类型')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        AfterSalesRequest::RESOLUTION_REFUND => '退款审批',
                        AfterSalesRequest::RESOLUTION_COUPON => '优惠码审批',
                        default => (string) $state,
                    })
                    ->badge(),
                TextColumn::make('user.name')->label('用户')->searchable(),
                TextColumn::make('order.order_number')->label('订单')->searchable()->toggleable(),
                TextColumn::make('coupon.code')->label('优惠码')->toggleable(),
                TextColumn::make('refund_amount_cents')
                    ->label('退款金额')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : Money::format((int) $state))
                    ->toggleable(),
                TextColumn::make('refundRequester.name')->label('申请人')->toggleable(),
                TextColumn::make('created_at')->label('申请时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('resolution_type')
                    ->label('审批类型')
                    ->options([
                        AfterSalesRequest::RESOLUTION_COUPON => '优惠码审批',
                        AfterSalesRequest::RESOLUTION_REFUND => '退款审批',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('approveCoupon')
                    ->label('同意发券')
                    ->icon(Heroicon::OutlinedTicket)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AfterSalesRequest $record): bool => static::isCouponApproval($record) && AdminAccess::canAction('coupons.issue'))
                    ->form([
                        Textarea::make('note')->label('审批备注')->rows(4),
                    ])
                    ->action(fn (AfterSalesRequest $record, array $data) => app(BackofficeApprovalService::class)->approveCouponRequest(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null,
                    )),
                Action::make('rejectCoupon')
                    ->label('拒绝发券')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AfterSalesRequest $record): bool => static::isCouponApproval($record) && AdminAccess::canAction('coupons.issue'))
                    ->form([
                        Textarea::make('note')->label('拒绝原因')->rows(4)->required(),
                    ])
                    ->action(fn (AfterSalesRequest $record, array $data) => app(BackofficeApprovalService::class)->rejectCouponRequest(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null,
                    )),
                Action::make('approveRefund')
                    ->label('同意退款')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AfterSalesRequest $record): bool => static::isRefundApproval($record) && AdminAccess::canAction('after_sales.refund'))
                    ->form([
                        Textarea::make('note')->label('审批备注')->rows(4),
                    ])
                    ->action(fn (AfterSalesRequest $record, array $data) => app(BackofficeApprovalService::class)->approveRefundRequest(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null,
                    )),
                Action::make('rejectRefund')
                    ->label('拒绝退款')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AfterSalesRequest $record): bool => static::isRefundApproval($record) && AdminAccess::canAction('after_sales.refund'))
                    ->form([
                        Textarea::make('note')->label('拒绝原因')->rows(4)->required(),
                    ])
                    ->action(fn (AfterSalesRequest $record, array $data) => app(BackofficeApprovalService::class)->rejectRefundRequest(
                        $record,
                        auth()->user(),
                        $data['note'] ?? null,
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
            'edit' => EditApprovalRequest::route('/{record}/edit'),
        ];
    }

    private static function isCouponApproval(AfterSalesRequest $record): bool
    {
        return $record->resolution_type === AfterSalesRequest::RESOLUTION_COUPON
            && filled($record->coupon_id)
            && ! in_array($record->status, [AfterSalesRequest::STATUS_RESOLVED, AfterSalesRequest::STATUS_CLOSED], true);
    }

    private static function isRefundApproval(AfterSalesRequest $record): bool
    {
        return $record->resolution_type === AfterSalesRequest::RESOLUTION_REFUND
            && $record->refund_status === AfterSalesRequest::REFUND_REQUESTED;
    }
}
