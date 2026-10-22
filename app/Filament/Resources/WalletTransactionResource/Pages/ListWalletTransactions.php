<?php

namespace App\Filament\Resources\WalletTransactionResource\Pages;

use App\Filament\Resources\WalletTransactionResource;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BackofficeApprovalService;
use App\Services\WalletService;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\MoneyInput;
use App\Support\RegexSearch;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    public function callMountedAction(array $arguments = []): mixed
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => [
                    str_starts_with($field, $statePath.'.') ? $field : $statePath.'.'.$field => $messages,
                ])->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustWallet')->label('调整钱包余额')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->visible(fn (): bool => AdminAccess::canAction('wallet.adjust'))
                ->schema([
                    Hidden::make('idempotency_key')->default(fn (): string => (string) Str::uuid())->required()->uuid(),
                    Select::make('user_id')->label('用户')->searchable()->required()
                        ->getSearchResultsUsing(fn (string $search): array => RegexSearch::where(User::query()->where('role', 'customer'), ['name', 'email', 'public_id'], $search)
                            ->limit(50)->get()->mapWithKeys(fn (User $user): array => [$user->id => $this->userLabel($user)])->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($user = User::query()->find($value)) ? $this->userLabel($user) : null),
                    Select::make('type')->label('操作')->options([
                        WalletTransaction::TYPE_CREDIT => '增加余额',
                        WalletTransaction::TYPE_DEBIT => '扣减余额',
                    ])->required(),
                    ...MoneyInput::convertedCents(TextInput::make('amount_cents')->label('金额')->minValue(0.01)->required()),
                    Textarea::make('reason')->label('调整原因')->required()->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    app(WalletService::class)->adjustBalance(
                        User::query()->where('role', 'customer')->findOrFail($data['user_id']),
                        (int) $data['amount_cents'], $data['type'], auth()->user(), $data['reason'], $data['idempotency_key'],
                    );
                    Notification::make()->title('钱包余额已调整')->success()->send();
                }),
            Action::make('refundToWallet')->label('订单退款到钱包')->icon(Heroicon::OutlinedReceiptRefund)
                ->visible(fn (): bool => AdminAccess::canAction('after_sales.refund'))
                ->schema([
                    Hidden::make('idempotency_key')->default(fn (): string => (string) Str::uuid())->required()->uuid(),
                    Select::make('order_id')->label('订单')->searchable()->required()
                        ->getSearchResultsUsing(fn (string $search): array => RegexSearch::where(Order::query()
                            ->where('payment_status', Order::PAYMENT_CONFIRMED)->where('is_wallet_recharge', false)
                            ->where('wallet_recharge_cents', 0), ['order_number'], $search)
                            ->with('user')->latest('id')->limit(50)->get()
                            ->mapWithKeys(fn (Order $order): array => [$order->id => $this->orderLabel($order)])->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($order = Order::query()->find($value)) ? $this->orderLabel($order) : null),
                    ...MoneyInput::convertedCents(TextInput::make('refund_amount_cents')->label('退款金额')->minValue(0.01)->required()),
                    Textarea::make('reason')->label('退款原因')->required()->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    app(BackofficeApprovalService::class)->refundOrderToWallet(
                        Order::query()->findOrFail($data['order_id']), auth()->user(),
                        (int) $data['refund_amount_cents'], $data['reason'], $data['idempotency_key'],
                    );
                    Notification::make()->title('退款已入账钱包')->success()->send();
                }),
        ];
    }

    private function userLabel(User $user): string
    {
        return $user->displayName().' / '.$user->public_id.' / '.Money::format((int) $user->wallet_balance_cents);
    }

    private function orderLabel(Order $order): string
    {
        return $order->order_number.' / '.$order->user?->displayName().' / '.Money::format(app(WalletService::class)->refundableOrderCents($order));
    }
}
