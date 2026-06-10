<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksAdminAccess;
use App\Filament\Resources\AfterSalesRequestResource\Pages\EditAfterSalesRequest;
use App\Filament\Resources\AfterSalesRequestResource\Pages\ListAfterSalesRequests;
use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Support\AdminAccess;
use App\Support\Money;
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

class AfterSalesRequestResource extends Resource
{
    use ChecksAdminAccess;

    protected static ?string $model = AfterSalesRequest::class;
    protected static string $permissionArea = 'support';
    protected static ?string $navigationLabel = '售后需求';
    protected static ?string $modelLabel = '售后需求';
    protected static ?string $pluralModelLabel = '售后需求';
    protected static string|\UnitEnum|null $navigationGroup = '客服';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;
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
            || AdminAccess::canAction('after_sales.request_refund')
            || AdminAccess::canAction('after_sales.refund');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('用户需求')->schema([
                Placeholder::make('user_name')->label('用户')->content(fn (?AfterSalesRequest $record): string => $record?->user?->displayName() ?? '-'),
                Placeholder::make('order_number')->label('订单号')->content(fn (?AfterSalesRequest $record): string => $record?->order?->order_number ?? '-'),
                TextInput::make('type')->label('类型')->disabled(),
                TextInput::make('subject')->label('主题')->disabled()->columnSpanFull(),
                Textarea::make('message')->label('说明')->disabled()->rows(6)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('处理')->schema([
                Select::make('status')->label('状态')->options([
                    AfterSalesRequest::STATUS_OPEN => '待处理',
                    AfterSalesRequest::STATUS_CONTACTING => '联系中',
                    AfterSalesRequest::STATUS_RESOLVED => '已处理',
                    AfterSalesRequest::STATUS_CLOSED => '已关闭',
                ])->required(),
                Select::make('resolution_type')->label('处理方式')->options(fn (): array => static::resolutionOptions()),
                TextInput::make('refund_amount_cents')->label('退款金额（分）')->numeric()->minValue(0)->visible(fn (): bool => AdminAccess::canAction('after_sales.refund')),
                Placeholder::make('refund_status_display')->label('退款审批')->content(fn (?AfterSalesRequest $record): string => static::refundStatusLabel($record?->refund_status)),
                Placeholder::make('refund_requested_by')->label('申请人')->content(fn (?AfterSalesRequest $record): string => $record?->refundRequester?->displayName() ?? '-'),
                Placeholder::make('refund_reviewed_by')->label('审批人')->content(fn (?AfterSalesRequest $record): string => $record?->refundReviewer?->displayName() ?? '-'),
                Select::make('coupon_id')
                    ->label('补偿优惠券')
                    ->options(fn (): array => Coupon::query()->latest()->limit(80)->pluck('code', 'id')->all())
                    ->searchable()
                    ->preload(),
                Textarea::make('admin_note')->label('处理留言')->rows(6)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label('主题')
                    ->searchable(query: fn (Builder $query, string $search): Builder => RegexSearch::where($query, ['subject', 'message', 'admin_note'], $search))
                    ->sortable(),
                TextColumn::make('user.name')->label('用户')->searchable(),
                TextColumn::make('order.order_number')->label('订单号')->searchable()->toggleable(),
                TextColumn::make('type')->label('类型')->badge(),
                TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AfterSalesRequest::STATUS_CONTACTING => '联系中',
                        AfterSalesRequest::STATUS_RESOLVED => '已处理',
                        AfterSalesRequest::STATUS_CLOSED => '已关闭',
                        default => '待处理',
                    })
                    ->badge(),
                TextColumn::make('resolution_type')->label('处理方式')->badge()->toggleable(),
                TextColumn::make('refund_status')->label('退款审批')->formatStateUsing(fn (?string $state): string => static::refundStatusLabel($state))->badge()->toggleable(),
                TextColumn::make('refund_amount_cents')->label('退款金额')->formatStateUsing(fn ($state): string => $state === null ? '-' : Money::format((int) $state))->toggleable(),
                TextColumn::make('created_at')->label('创建')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('contacting')
                    ->label('标记联系中')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('info')
                    ->visible(fn (AfterSalesRequest $record): bool => AdminAccess::canAction('after_sales.resolve') && $record->status !== AfterSalesRequest::STATUS_RESOLVED)
                    ->action(fn (AfterSalesRequest $record) => $record->update(['status' => AfterSalesRequest::STATUS_CONTACTING])),
                Action::make('requestRefund')
                    ->label('发起退款申请')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('warning')
                    ->form([
                        TextInput::make('refund_amount_cents')->label('退款金额（分）')->numeric()->minValue(0)->required(),
                        Textarea::make('admin_note')->label('申请说明')->rows(4)->required(),
                    ])
                    ->visible(fn (AfterSalesRequest $record): bool => AdminAccess::canAction('after_sales.request_refund')
                        && ! in_array($record->status, [AfterSalesRequest::STATUS_RESOLVED, AfterSalesRequest::STATUS_CLOSED], true)
                        && ! in_array($record->refund_status, [AfterSalesRequest::REFUND_REQUESTED, AfterSalesRequest::REFUND_APPROVED], true))
                    ->action(fn (AfterSalesRequest $record, array $data) => $record->update([
                        'status' => AfterSalesRequest::STATUS_CONTACTING,
                        'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                        'refund_amount_cents' => (int) $data['refund_amount_cents'],
                        'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
                        'refund_requested_by_id' => auth()->id(),
                        'refund_requested_at' => now(),
                        'refund_reviewed_by_id' => null,
                        'refund_reviewed_at' => null,
                        'admin_note' => static::appendNote($record->admin_note, $data['admin_note'] ?? null),
                    ])),
                Action::make('approveRefund')
                    ->label('审批退款')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_note')->label('审批备注')->rows(4),
                    ])
                    ->visible(fn (AfterSalesRequest $record): bool => AdminAccess::canAction('after_sales.refund') && $record->refund_status === AfterSalesRequest::REFUND_REQUESTED)
                    ->action(fn (AfterSalesRequest $record, array $data) => $record->update([
                        'status' => AfterSalesRequest::STATUS_RESOLVED,
                        'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                        'refund_status' => AfterSalesRequest::REFUND_APPROVED,
                        'refund_reviewed_by_id' => auth()->id(),
                        'refund_reviewed_at' => now(),
                        'admin_note' => static::appendNote($record->admin_note, $data['admin_note'] ?? null),
                        'resolved_at' => now(),
                    ])),
                Action::make('rejectRefund')
                    ->label('驳回退款')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_note')->label('驳回原因')->rows(4)->required(),
                    ])
                    ->visible(fn (AfterSalesRequest $record): bool => AdminAccess::canAction('after_sales.refund') && $record->refund_status === AfterSalesRequest::REFUND_REQUESTED)
                    ->action(fn (AfterSalesRequest $record, array $data) => $record->update([
                        'status' => AfterSalesRequest::STATUS_CONTACTING,
                        'refund_status' => AfterSalesRequest::REFUND_REJECTED,
                        'refund_reviewed_by_id' => auth()->id(),
                        'refund_reviewed_at' => now(),
                        'admin_note' => static::appendNote($record->admin_note, $data['admin_note'] ?? null),
                    ])),
                Action::make('resolve')
                    ->label('快速处理')
                    ->icon(Heroicon::OutlinedCheck)
                    ->form([
                        Select::make('resolution_type')->label('处理方式')->options(fn (): array => static::resolutionOptions())->default(AfterSalesRequest::RESOLUTION_MESSAGE)->required(),
                        TextInput::make('refund_amount_cents')->label('退款金额（分）')->numeric()->minValue(0)->visible(fn (): bool => AdminAccess::canAction('after_sales.refund')),
                        Select::make('coupon_id')
                            ->label('补偿优惠券')
                            ->options(fn (): array => Coupon::query()->latest()->limit(80)->pluck('code', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Textarea::make('admin_note')->label('处理留言')->rows(4)->required(),
                    ])
                    ->visible(fn (): bool => AdminAccess::canAction('after_sales.resolve'))
                    ->action(function (AfterSalesRequest $record, array $data): void {
                        $resolutionType = (string) $data['resolution_type'];
                        $canRefund = AdminAccess::canAction('after_sales.refund');

                        if ($resolutionType === AfterSalesRequest::RESOLUTION_REFUND && ! $canRefund) {
                            $resolutionType = AfterSalesRequest::RESOLUTION_MESSAGE;
                        }

                        $record->update([
                            'status' => AfterSalesRequest::STATUS_RESOLVED,
                            'resolution_type' => $resolutionType,
                            'refund_amount_cents' => $resolutionType === AfterSalesRequest::RESOLUTION_REFUND ? ($data['refund_amount_cents'] ?? null) : null,
                            'refund_status' => $resolutionType === AfterSalesRequest::RESOLUTION_REFUND ? AfterSalesRequest::REFUND_APPROVED : $record->refund_status,
                            'refund_reviewed_by_id' => $resolutionType === AfterSalesRequest::RESOLUTION_REFUND ? auth()->id() : $record->refund_reviewed_by_id,
                            'refund_reviewed_at' => $resolutionType === AfterSalesRequest::RESOLUTION_REFUND ? now() : $record->refund_reviewed_at,
                            'coupon_id' => $data['coupon_id'] ?? null,
                            'admin_note' => $data['admin_note'],
                            'resolved_at' => now(),
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAfterSalesRequests::route('/'),
            'edit' => EditAfterSalesRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function resolutionOptions(): array
    {
        $options = [
            AfterSalesRequest::RESOLUTION_COUPON => '发放优惠券补偿',
            AfterSalesRequest::RESOLUTION_MESSAGE => '留言处理',
        ];

        if (AdminAccess::canAction('after_sales.refund')) {
            $options = [AfterSalesRequest::RESOLUTION_REFUND => '退款'] + $options;
        }

        return $options;
    }

    private static function refundStatusLabel(?string $status): string
    {
        return match ($status) {
            AfterSalesRequest::REFUND_REQUESTED => '待审批',
            AfterSalesRequest::REFUND_APPROVED => '已同意',
            AfterSalesRequest::REFUND_REJECTED => '已驳回',
            default => '-',
        };
    }

    private static function appendNote(?string $oldNote, ?string $newNote): ?string
    {
        $newNote = trim((string) $newNote);

        if ($newNote === '') {
            return $oldNote;
        }

        $oldNote = trim((string) $oldNote);

        return $oldNote === '' ? $newNote : $oldNote.PHP_EOL.PHP_EOL.$newNote;
    }
}
