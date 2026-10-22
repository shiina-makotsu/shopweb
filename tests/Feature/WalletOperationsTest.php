<?php

use App\Filament\Resources\AfterSalesRequestResource\Pages\EditAfterSalesRequest;
use App\Filament\Resources\AfterSalesRequestResource\Pages\ListAfterSalesRequests;
use App\Filament\Resources\WalletTransactionResource;
use App\Filament\Resources\WalletTransactionResource\Pages\ListWalletTransactions;
use App\Models\AdminActivityLog;
use App\Models\AfterSalesRequest;
use App\Models\Order;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdminActivityLogger;
use App\Services\AfterSalesResolutionService;
use App\Services\BackofficeApprovalService;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function walletOperationsOrder(User $user, array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'user_id' => $user->id,
        'order_number' => 'WALLET-'.Str::uuid(),
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'wallet_payment_cents' => 0,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ], $attributes));
}

function walletOperationsRequest(Order $order, int $amountCents = 5000, array $attributes = []): AfterSalesRequest
{
    return AfterSalesRequest::query()->create(array_merge([
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'type' => 'wallet_refund',
        'status' => AfterSalesRequest::STATUS_OPEN,
        'subject' => 'Refund to wallet',
        'message' => 'Please refund to my wallet.',
        'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
        'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
        'refund_amount_cents' => $amountCents,
    ], $attributes));
}

it('credits the approved amount for every payment split and audits approval once', function (int $walletPaid, int $externalPaid, int $refund): void {
    $user = User::factory()->create(['wallet_balance_cents' => 100]);
    $finance = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user, ['wallet_payment_cents' => $walletPaid, 'total_cents' => $externalPaid]);
    $request = walletOperationsRequest($order, $refund);
    $service = app(BackofficeApprovalService::class);

    $service->approveRefundRequest($request, $finance, 'Approved refund.');
    $reviewedAt = $request->fresh()->refund_reviewed_at;
    $service->approveRefundRequest($request, $finance, 'Repeated click.');

    expect((int) $user->fresh()->wallet_balance_cents)->toBe(100 + $refund)
        ->and($request->fresh()->refund_status)->toBe(AfterSalesRequest::REFUND_APPROVED)
        ->and($request->fresh()->refund_reviewed_at->equalTo($reviewedAt))->toBeTrue()
        ->and(WalletTransaction::query()->count())->toBe(1);
    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $user->id, 'order_id' => $order->id, 'after_sales_request_id' => $request->id,
        'created_by_user_id' => $finance->id, 'source' => WalletTransaction::SOURCE_ORDER_REFUND,
        'amount_cents' => $refund, 'balance_after_cents' => 100 + $refund,
        'idempotency_key' => 'after-sales:'.$request->id,
    ]);
})->with([[0, 10000, 7000], [3000, 7000, 8000], [10000, 0, 10000]]);

it('keeps cancellation refunds limited to original wallet payments and counts them against later approvals', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user, ['wallet_payment_cents' => 3000, 'total_cents' => 7000]);
    $wallets = app(WalletService::class);

    expect($wallets->refundOrderPayment($order, 10000)?->amount_cents)->toBe(3000)
        ->and($wallets->refundOrderPayment($order, 10000))->toBeNull();
    app(BackofficeApprovalService::class)->approveRefundRequest(walletOperationsRequest($order, 7000), $actor, 'Refund remaining external payment.');

    expect((int) $user->fresh()->wallet_balance_cents)->toBe(10000)
        ->and($wallets->refundOrderPayment($order, 3000))->toBeNull()
        ->and($wallets->refundableOrderCents($order))->toBe(0);
    $externalOnly = walletOperationsOrder($user);
    expect($wallets->refundOrderPayment($externalOnly, 10000))->toBeNull();
});

it('atomically rejects cumulative over-refunds across distinct aftersales requests', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user);
    $service = app(BackofficeApprovalService::class);
    $service->approveRefundRequest(walletOperationsRequest($order, 7000), $actor, 'First partial refund.');
    $second = walletOperationsRequest($order, 4000);

    expect(fn () => $service->approveRefundRequest($second, $actor, 'Too much.'))->toThrow(ValidationException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(7000)
        ->and($second->fresh()->refund_status)->toBe(AfterSalesRequest::REFUND_REQUESTED)
        ->and($second->fresh()->refund_reviewed_at)->toBeNull()
        ->and(WalletTransaction::query()->count())->toBe(1);
});

it('uses the same full-amount refund path for quick resolution and prevents rewriting paid refunds', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'sales']);
    $request = walletOperationsRequest(walletOperationsOrder($user));
    $service = app(AfterSalesResolutionService::class);
    $data = ['resolution_type' => 'refund', 'refund_amount_cents' => 6000, 'admin_note' => 'Resolved to wallet.'];

    $service->resolve($request, $data, $actor);
    $service->resolve($request, $data, $actor);
    expect(fn () => $service->resolve($request, array_replace($data, ['refund_amount_cents' => 6500]), $actor))->toThrow(ValidationException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(6000)
        ->and(WalletTransaction::query()->count())->toBe(1);
});

it('rejects invalid payment states and ownership mismatches without changing approvals', function (array $orderAttributes, bool $wrongUser): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user, $orderAttributes);
    $request = walletOperationsRequest($order, 1000, $wrongUser ? ['user_id' => $actor->id] : []);

    expect(fn () => app(BackofficeApprovalService::class)->approveRefundRequest($request, $actor, 'Invalid.'))->toThrow(ValidationException::class);
    expect($request->fresh()->refund_status)->toBe(AfterSalesRequest::REFUND_REQUESTED)
        ->and((int) $user->fresh()->wallet_balance_cents)->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(0);
})->with([
    [['payment_status' => Order::PAYMENT_PENDING], false],
    [['payment_status' => Order::PAYMENT_SUBMITTED], false],
    [['is_wallet_recharge' => true, 'wallet_recharge_cents' => 10000], false],
    [[], true],
]);

it('does not replay historical approved refunds without ledger links or reverse an approved audit', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 4000]);
    $actor = User::factory()->create(['role' => 'finance']);
    $request = walletOperationsRequest(walletOperationsOrder($user), 4000, [
        'refund_status' => AfterSalesRequest::REFUND_APPROVED, 'status' => AfterSalesRequest::STATUS_RESOLVED,
    ]);
    app(BackofficeApprovalService::class)->approveRefundRequest($request, $actor, 'Retry legacy approval.');
    expect(fn () => app(BackofficeApprovalService::class)->rejectRefundRequest($request, $actor, 'Reject too late.'))->toThrow(ValidationException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(4000)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('requires refund authority inside services even when called outside the admin UI', function (string $role): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => $role]);
    $order = walletOperationsOrder($user);
    $request = walletOperationsRequest($order);

    expect(fn () => app(BackofficeApprovalService::class)->approveRefundRequest($request, $actor, 'Unauthorized.'))->toThrow(HttpException::class)
        ->and(fn () => app(AfterSalesResolutionService::class)->resolve($request, ['resolution_type' => 'refund', 'admin_note' => 'Unauthorized.', 'refund_amount_cents' => 5000], $actor))->toThrow(HttpException::class)
        ->and(fn () => app(BackofficeApprovalService::class)->refundOrderToWallet($order, $actor, 1000, 'Unauthorized.', (string) Str::uuid()))->toThrow(HttpException::class);
    expect(WalletTransaction::query()->count())->toBe(0);
})->with(['customer', 'support', 'operator', 'warehouse']);

it('audits direct order refunds with reasons and replays the same operation only once', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user);
    $key = (string) Str::uuid();
    $service = app(BackofficeApprovalService::class);
    $request = $service->refundOrderToWallet($order, $actor, 5000, 'Direct approved refund.', $key);

    expect($service->refundOrderToWallet($order, $actor, 5000, 'Direct approved refund.', $key)->id)->toBe($request->id)
        ->and(fn () => $service->refundOrderToWallet($order, $actor, 5500, 'Direct approved refund.', $key))->toThrow(ValidationException::class)
        ->and(fn () => $service->refundOrderToWallet($order, $actor, 5000, ' ', (string) Str::uuid()))->toThrow(ValidationException::class)
        ->and(fn () => $service->refundOrderToWallet($order, $actor, 5001, 'Over refund.', (string) Str::uuid()))->toThrow(ValidationException::class)
        ->and((int) $user->fresh()->wallet_balance_cents)->toBe(5000)
        ->and(AfterSalesRequest::query()->count())->toBe(1)
        ->and(WalletTransaction::query()->count())->toBe(1);

    $log = AdminActivityLog::query()->sole();
    expect($log->action)->toBe('order_refunded_to_wallet')
        ->and($log->actionLabel())->toBe('后台订单退款到钱包')
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->subject->is($order))->toBeTrue()
        ->and($log->description)->toContain('后台订单退款到钱包', $order->order_number, 'Direct approved refund.')
        ->and($log->properties)->toMatchArray([
            'customer_user_id' => $user->id,
            'wallet_transaction_id' => WalletTransaction::query()->sole()->id,
            'after_sales_request_id' => $request->id,
            'amount_cents' => 5000, 'balance_before_cents' => 0, 'balance_after_cents' => 5000,
            'reason' => 'Direct approved refund.',
        ]);
});

it('credits direct chat refunds and rolls back invalid chat refunds', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'sales']);
    $order = walletOperationsOrder($user);
    $session = SupportChatSession::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => SupportChatSession::STATUS_ACTIVE]);
    $service = app(BackofficeApprovalService::class);
    $service->approveRefundForChat($session, $actor, 7000, 'Direct refund.');
    expect(fn () => $service->approveRefundForChat($session, $actor, 4000, 'Over refund.'))->toThrow(ValidationException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(7000)
        ->and(AfterSalesRequest::query()->count())->toBe(1)
        ->and($session->messages()->count())->toBe(1);
});

it('allows administrators and finance to credit and debit with immutable idempotent audit details', function (string $role): void {
    $user = User::factory()->create(['wallet_balance_cents' => 1000]);
    $actor = User::factory()->create(['role' => $role]);
    $wallets = app(WalletService::class);
    $key = (string) Str::uuid();
    $credit = $wallets->adjustBalance($user, 2500, 'credit', $actor, 'Account correction.', $key);
    $debitKey = (string) Str::uuid();
    $debit = $wallets->adjustBalance($user, 500, 'debit', $actor, 'Deduct agreed fee.', $debitKey);

    expect($wallets->adjustBalance($user, 2500, 'credit', $actor, 'Account correction.', $key)->id)->toBe($credit->id)
        ->and($wallets->adjustBalance($user, 500, 'debit', $actor, 'Deduct agreed fee.', $debitKey)->id)->toBe($debit->id)
        ->and(fn () => $wallets->adjustBalance($user, 2501, 'credit', $actor, 'Account correction.', $key))->toThrow(ValidationException::class)
        ->and((int) $debit->amount_cents)->toBe(-500)
        ->and((int) $user->fresh()->wallet_balance_cents)->toBe(3000)
        ->and(WalletTransaction::query()->count())->toBe(2);
    $this->assertDatabaseHas('wallet_transactions', [
        'id' => $credit->id, 'created_by_user_id' => $actor->id, 'note' => 'Account correction.',
        'source' => WalletTransaction::SOURCE_ADMIN, 'balance_after_cents' => 3500,
    ]);
    expect(AdminActivityLog::query()->count())->toBe(2);
    foreach ([$credit, $debit] as $transaction) {
        $isCredit = $transaction->type === WalletTransaction::TYPE_CREDIT;
        $log = AdminActivityLog::query()->where('action', $isCredit ? 'wallet_balance_credited' : 'wallet_balance_debited')->sole();
        expect($log->user_id)->toBe($actor->id)
            ->and($log->subject->is($transaction))->toBeTrue()
            ->and($log->actionLabel())->toBe($isCredit ? '后台增加钱包余额' : '后台扣减钱包余额')
            ->and($log->description)->toContain($isCredit ? '后台增加钱包余额' : '后台扣减钱包余额', $transaction->note)
            ->and($log->properties)->toMatchArray([
                'customer_user_id' => $user->id, 'wallet_transaction_id' => $transaction->id,
                'amount_cents' => $isCredit ? 2500 : -500,
                'balance_before_cents' => $isCredit ? 1000 : 3500,
                'balance_after_cents' => $isCredit ? 3500 : 3000,
                'reason' => $transaction->note,
            ]);
    }
})->with(['admin', 'finance']);

it('prevents overdrafts invalid adjustments and blank reasons without writing ledger rows', function (int $amount, string $type, string $reason): void {
    $user = User::factory()->create(['wallet_balance_cents' => 1000]);
    $actor = User::factory()->create(['role' => 'finance']);
    expect(fn () => app(WalletService::class)->adjustBalance($user, $amount, $type, $actor, $reason, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(1000)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and(AdminActivityLog::query()->count())->toBe(0);
})->with([[1001, 'debit', 'No overdraft.'], [0, 'credit', 'Invalid.'], [-1, 'debit', 'Invalid.'], [1, 'invalid', 'Invalid.'], [100, 'credit', '  ']]);

it('blocks wallet adjustments for non-financial staff at the service boundary', function (string $role): void {
    $user = User::factory()->create(['wallet_balance_cents' => 1000]);
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => app(WalletService::class)->adjustBalance($user, 100, 'debit', $actor, 'Unauthorized.', (string) Str::uuid()))->toThrow(HttpException::class);
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(1000)
        ->and(AdminActivityLog::query()->count())->toBe(0);
})->with(['support', 'sales', 'operator', 'customer', 'warehouse']);

it('rolls back financial changes when the required admin operation log cannot be written', function (string $operation): void {
    $user = User::factory()->create(['wallet_balance_cents' => 1000]);
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user);
    $this->mock(AdminActivityLogger::class, function ($mock): void {
        $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });

    expect(fn () => $operation === 'refund'
        ? app(BackofficeApprovalService::class)->refundOrderToWallet($order, $actor, 500, 'Refund reason.', (string) Str::uuid())
        : app(WalletService::class)->adjustBalance($user, 500, $operation, $actor, 'Adjustment reason.', (string) Str::uuid())
    )->toThrow(RuntimeException::class, 'Audit unavailable.');
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(1000)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and(AfterSalesRequest::query()->count())->toBe(0)
        ->and(AdminActivityLog::query()->count())->toBe(0);
})->with(['credit', 'debit', 'refund']);

it('reuses existing aftersales routes for customer wallet requests history and authorization', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $other = User::factory()->create();
    $actor = User::factory()->create(['role' => 'finance']);
    $order = walletOperationsOrder($user);
    $data = ['type' => 'wallet_refund', 'refund_amount' => '25.50', 'subject' => 'Wallet refund request', 'message' => 'Refund this amount to my wallet.'];

    $this->actingAs($other)->post(route('orders.after-sales.store', $order), $data)->assertForbidden();
    $this->actingAs($user)->post(route('orders.after-sales.store', $order), array_replace($data, ['refund_amount' => '100.01']))->assertSessionHasErrors('refund_amount_cents');
    $this->actingAs($user)->post(route('orders.after-sales.store', $order), $data)->assertRedirect(route('orders.after-sales', $order));
    $request = AfterSalesRequest::query()->sole();
    expect((int) $request->refund_amount_cents)->toBe(2550)
        ->and($request->refund_status)->toBe(AfterSalesRequest::REFUND_REQUESTED)
        ->and((int) $user->fresh()->wallet_balance_cents)->toBe(0);
    $this->post(route('orders.after-sales.store', $order), $data)->assertSessionHasErrors('refund_amount');
    $this->get(route('orders.after-sales', $order))->assertOk()->assertSee('退款到钱包')->assertSee('待审批');
    app(BackofficeApprovalService::class)->approveRefundRequest($request, $actor, 'Wallet approved.');
    $this->get(route('orders.after-sales', $order))->assertOk()->assertSee('已同意')->assertSee('Wallet approved.');
    $this->get(route('user.section', 'wallet'))->assertOk()->assertSee('Wallet approved.')
        ->assertSee('订单退款')->assertDontSee('order_refund');
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(2550);
});

it('links the wallet refund entry through order pagination to the preselected aftersales form', function (): void {
    $user = User::factory()->create();
    $order = walletOperationsOrder($user);
    $order->forceFill(['created_at' => now()->subDay()])->save();
    for ($i = 0; $i < 12; $i++) {
        walletOperationsOrder($user);
    }
    $otherOrder = walletOperationsOrder(User::factory()->create());
    $this->actingAs($user)->get(route('user.section', 'wallet'))->assertOk()
        ->assertSee('申请订单退款')->assertSee('选择订单后提交退款到钱包申请')
        ->assertSee(route('orders.index', ['type' => 'wallet_refund'], false));
    $this->get(route('orders.index', ['type' => 'wallet_refund']))->assertOk()
        ->assertSee(route('orders.index', ['type' => 'wallet_refund', 'page' => 2], false))
        ->assertDontSee(route('orders.after-sales', ['order' => $otherOrder, 'type' => 'wallet_refund'], false));
    $this->get(route('orders.index', ['type' => 'wallet_refund', 'page' => 2]))->assertOk()
        ->assertSee(route('orders.after-sales', ['order' => $order, 'type' => 'wallet_refund'], false));
    $this->get(route('orders.after-sales', ['order' => $order, 'type' => 'wallet_refund']))->assertOk()
        ->assertSee('value="wallet_refund" selected', false);
    $this->withSession(['_old_input' => ['type' => 'repair']])
        ->get(route('orders.after-sales', ['order' => $order, 'type' => 'wallet_refund']))->assertOk()
        ->assertSee('value="repair" selected', false);
});

it('does not preselect wallet refunds for ineligible orders', function (array $attributes): void {
    $user = User::factory()->create();
    $order = walletOperationsOrder($user, $attributes);
    $this->actingAs($user)->get(route('orders.after-sales', ['order' => $order, 'type' => 'wallet_refund']))->assertOk()
        ->assertSee('value="refund" selected', false)->assertDontSee('value="wallet_refund"', false);
})->with([
    [['payment_status' => Order::PAYMENT_PENDING]],
    [['is_wallet_recharge' => true, 'wallet_recharge_cents' => 10000]],
]);

it('provides Chinese labels for every wallet source constant', function (): void {
    $sources = collect((new ReflectionClass(WalletTransaction::class))->getConstants())
        ->filter(fn ($value, $name) => str_starts_with($name, 'SOURCE_'))->values()->sort()->values()->all();
    expect(collect(array_keys(WalletTransaction::sourceLabels()))->sort()->values()->all())->toBe($sources);
    foreach (WalletTransaction::sourceLabels() as $label) {
        expect(preg_match('/\p{Han}/u', $label))->toBe(1);
    }
});

it('labels Telegram support claims in the operation audit UI', function (): void {
    expect((new AdminActivityLog(['action' => 'support.telegram_accept']))->actionLabel())->toBe('通过 Telegram 接受客服会话');
});

it('renders the ledger and executes permissioned admin wallet controls', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 1000]);
    $finance = User::factory()->create(['role' => 'finance']);
    $sales = User::factory()->create(['role' => 'sales']);
    $support = User::factory()->create(['role' => 'support']);
    $order = walletOperationsOrder($user);
    $this->actingAs($support)->get(WalletTransactionResource::getUrl())->assertForbidden();
    Livewire::actingAs($sales)->test(ListWalletTransactions::class)->assertActionHidden('adjustWallet')->assertActionVisible('refundToWallet');
    Livewire::actingAs($finance)->test(ListWalletTransactions::class)
        ->callAction('adjustWallet', [
            'user_id' => $user->id, 'type' => 'debit', 'amount_cents' => '10.01',
            'reason' => 'Cannot overdraw.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertHasActionErrors(['amount_cents']);
    Livewire::actingAs($finance)->test(ListWalletTransactions::class)
        ->callAction('adjustWallet', [
            'user_id' => $user->id, 'type' => 'debit', 'amount_cents' => '2.50',
            'reason' => 'Admin debit.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertHasNoActionErrors();
    Livewire::actingAs($finance)->test(ListWalletTransactions::class)
        ->callAction('refundToWallet', [
            'order_id' => $order->id, 'refund_amount_cents' => '100.01',
            'reason' => 'Cannot over-refund.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertHasActionErrors(['refund_amount_cents']);
    Livewire::actingAs($finance)->test(ListWalletTransactions::class)
        ->callAction('refundToWallet', [
            'order_id' => $order->id, 'refund_amount_cents' => '12.50',
            'reason' => 'Admin direct refund.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertHasNoActionErrors();
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(2000);
    Livewire::actingAs($finance)->test(ListWalletTransactions::class)
        ->assertTableColumnFormattedStateSet('source', '后台调整', WalletTransaction::query()->where('source', WalletTransaction::SOURCE_ADMIN)->sole())
        ->assertTableColumnFormattedStateSet('source', '订单退款', WalletTransaction::query()->where('source', WalletTransaction::SOURCE_ORDER_REFUND)->sole());
    $this->actingAs($finance)->get(WalletTransactionResource::getUrl())->assertOk()->assertSee('Admin direct refund.')->assertSee('Admin debit.');
});

it('executes refund resolution from the aftersales edit form instead of only marking it resolved', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $finance = User::factory()->create(['role' => 'finance']);
    $request = walletOperationsRequest(walletOperationsOrder($user), 1250);
    Livewire::actingAs($finance)->test(EditAfterSalesRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm(['status' => AfterSalesRequest::STATUS_RESOLVED, 'resolution_type' => 'refund', 'refund_amount_cents' => '100.01', 'admin_note' => 'Invalid refund.'])
        ->call('save')->assertHasFormErrors(['refund_amount_cents']);
    Livewire::actingAs($finance)->test(ListAfterSalesRequests::class)
        ->callTableAction('resolve', $request, ['resolution_type' => 'refund', 'refund_amount_cents' => '100.01', 'admin_note' => 'Invalid refund.'])
        ->assertHasTableActionErrors(['refund_amount_cents']);
    Livewire::actingAs($finance)->test(EditAfterSalesRequest::class, ['record' => $request->getRouteKey()])
        ->fillForm(['status' => AfterSalesRequest::STATUS_RESOLVED, 'resolution_type' => 'refund', 'refund_amount_cents' => '12.50', 'admin_note' => 'Edit refund.'])
        ->call('save')->assertHasNoFormErrors();
    expect((int) $user->fresh()->wallet_balance_cents)->toBe(1250)
        ->and($request->fresh()->refund_status)->toBe(AfterSalesRequest::REFUND_APPROVED)
        ->and(WalletTransaction::query()->count())->toBe(1);
});

it('rejects pending refunds without crediting wallets and disallows approval of rejected requests', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $request = walletOperationsRequest(walletOperationsOrder($user));
    $service = app(BackofficeApprovalService::class);

    $service->rejectRefundRequest($request, $actor, 'Refund not justified.');
    expect(fn () => $service->approveRefundRequest($request, $actor, 'Stale approve.'))->toThrow(ValidationException::class);
    expect($request->fresh()->refund_status)->toBe(AfterSalesRequest::REFUND_REJECTED)
        ->and((int) $user->fresh()->wallet_balance_cents)->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('validates customer refund amounts before creating any financial request', function (mixed $amount): void {
    $user = User::factory()->create();
    $order = walletOperationsOrder($user);
    $this->actingAs($user)->post(route('orders.after-sales.store', $order), [
        'type' => 'wallet_refund', 'subject' => 'Invalid amount', 'message' => 'Please review.', 'refund_amount' => $amount,
    ])->assertSessionHasErrors('refund_amount');
    expect(AfterSalesRequest::query()->count())->toBe(0);
})->with([null, '0', '-1', '1.001', 'not-money']);

it('enforces unique refund audit links at the database boundary', function (): void {
    $user = User::factory()->create(['wallet_balance_cents' => 0]);
    $actor = User::factory()->create(['role' => 'finance']);
    $request = walletOperationsRequest(walletOperationsOrder($user));
    app(BackofficeApprovalService::class)->approveRefundRequest($request, $actor, 'Approved once.');
    $transaction = WalletTransaction::query()->sole();

    expect(app(WalletService::class)->refundAfterSalesRequest($request, $actor)->id)->toBe($transaction->id);
    $duplicate = $transaction->only($transaction->getFillable());
    $duplicate['idempotency_key'] = null;
    expect(fn () => WalletTransaction::query()->create($duplicate))->toThrow(QueryException::class);
    $duplicate['after_sales_request_id'] = null;
    $duplicate['idempotency_key'] = $transaction->idempotency_key;
    expect(fn () => WalletTransaction::query()->create($duplicate))->toThrow(QueryException::class);
    expect(WalletTransaction::query()->count())->toBe(1);
});

it('can roll back and reapply only the wallet idempotency migration', function (): void {
    $migration = require database_path('migrations/2026_10_22_000001_add_wallet_operation_idempotency.php');
    $migration->down();
    expect(Schema::hasColumn('wallet_transactions', 'idempotency_key'))->toBeFalse()
        ->and(Schema::hasColumn('wallet_transactions', 'after_sales_request_id'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('wallet_transactions', 'idempotency_key'))->toBeTrue()
        ->and(Schema::hasColumn('wallet_transactions', 'after_sales_request_id'))->toBeTrue();
});
