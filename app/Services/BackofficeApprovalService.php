<?php

namespace App\Services;

use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletTransaction;
use App\Support\AdminAccess;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BackofficeApprovalService
{
    public function requestWalletRefund(Order $order, User $customer, int $amountCents, string $subject, string $message): AfterSalesRequest
    {
        return DB::transaction(function () use ($order, $customer, $amountCents, $subject, $message): AfterSalesRequest {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $order->user_id === (int) $customer->id, 403);
            abort_if($order->user_deleted_at, 404);
            app(WalletService::class)->validateOrderRefund($order, $amountCents);

            if (AfterSalesRequest::query()->where('order_id', $order->id)
                ->where('refund_status', AfterSalesRequest::REFUND_REQUESTED)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['refund_amount' => '该订单已有待审批退款申请。']);
            }

            return AfterSalesRequest::query()->create([
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'type' => 'wallet_refund',
                'status' => AfterSalesRequest::STATUS_OPEN,
                'subject' => $subject,
                'message' => $message,
                'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                'refund_amount_cents' => $amountCents,
                'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
                'refund_requested_by_id' => $customer->id,
                'refund_requested_at' => now(),
            ]);
        });
    }

    public function requestRefundForAfterSales(AfterSalesRequest $request, User $requester, int $amountCents, string $note): void
    {
        abort_unless(AdminAccess::canAction('after_sales.request_refund', $requester), 403);

        if (trim($note) === '' || $amountCents <= 0) {
            throw ValidationException::withMessages(['admin_note' => '请输入申请原因和有效退款金额。']);
        }

        DB::transaction(function () use ($request, $requester, $amountCents, $note): void {
            $request = AfterSalesRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (in_array($request->status, [AfterSalesRequest::STATUS_RESOLVED, AfterSalesRequest::STATUS_CLOSED], true)
                || in_array($request->refund_status, [AfterSalesRequest::REFUND_REQUESTED, AfterSalesRequest::REFUND_APPROVED], true)
                || ! $request->order || (int) $request->order->user_id !== (int) $request->user_id) {
                throw ValidationException::withMessages(['refund_status' => '该售后记录不能发起退款申请。']);
            }

            app(WalletService::class)->validateOrderRefund($request->order, $amountCents);
            $request->update([
                'status' => AfterSalesRequest::STATUS_CONTACTING,
                'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                'refund_amount_cents' => $amountCents,
                'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
                'refund_requested_by_id' => $requester->id,
                'refund_requested_at' => now(),
                'refund_reviewed_by_id' => null,
                'refund_reviewed_at' => null,
                'admin_note' => $request->mergedAdminNote($note),
            ]);
        });
    }

    public function requestCouponFromChat(SupportChatSession $session, Coupon $coupon, User $requester, ?string $note = null): AfterSalesRequest
    {
        $this->ensureCustomerSession($session);

        $request = AfterSalesRequest::query()->create([
            'user_id' => $session->user_id,
            'order_id' => $session->order_id,
            'type' => 'coupon',
            'status' => AfterSalesRequest::STATUS_CONTACTING,
            'subject' => '客服会话优惠码申请',
            'message' => $this->messageOrDefault($note, '客服在会话中申请向用户发放优惠码。'),
            'admin_note' => $this->operatorNote($requester, $note),
            'resolution_type' => AfterSalesRequest::RESOLUTION_COUPON,
            'coupon_id' => $coupon->id,
        ]);

        $this->addSystemMessage($session, sprintf('已提交优惠码发放申请：%s。', $coupon->code));

        return $request;
    }

    public function issueCouponForChat(SupportChatSession $session, Coupon $coupon, User $issuer, ?string $note = null): UserCoupon
    {
        $this->ensureCustomerSession($session);

        $request = AfterSalesRequest::query()->create([
            'user_id' => $session->user_id,
            'order_id' => $session->order_id,
            'type' => 'coupon',
            'status' => AfterSalesRequest::STATUS_RESOLVED,
            'subject' => '客服会话发放优惠码',
            'message' => $this->messageOrDefault($note, '客服在会话中直接向用户发放优惠码。'),
            'admin_note' => $this->operatorNote($issuer, $note),
            'resolution_type' => AfterSalesRequest::RESOLUTION_COUPON,
            'coupon_id' => $coupon->id,
            'resolved_at' => now(),
        ]);

        $userCoupon = app(CouponService::class)->issueToUser(
            $coupon,
            $session->user,
            UserCoupon::SOURCE_AFTER_SALES,
            $issuer,
            $request->id,
            $note,
        );

        $this->addSystemMessage($session, sprintf('已发放优惠码：%s。', $coupon->code));

        return $userCoupon;
    }

    public function requestRefundFromChat(SupportChatSession $session, User $requester, int $amountCents, ?string $note = null): AfterSalesRequest
    {
        abort_unless(AdminAccess::canAction('after_sales.request_refund', $requester), 403);
        $this->ensureOrderSession($session);
        app(WalletService::class)->validateOrderRefund($session->order, $amountCents);

        $request = AfterSalesRequest::query()->create([
            'user_id' => $session->user_id,
            'order_id' => $session->order_id,
            'type' => 'refund',
            'status' => AfterSalesRequest::STATUS_CONTACTING,
            'subject' => '客服会话退款申请',
            'message' => $this->messageOrDefault($note, '客服在会话中为该订单发起退款申请。'),
            'admin_note' => $this->operatorNote($requester, $note),
            'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
            'refund_amount_cents' => $amountCents,
            'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
            'refund_requested_by_id' => $requester->id,
            'refund_requested_at' => now(),
        ]);

        $this->addSystemMessage($session, '已提交退款申请。');

        return $request;
    }

    public function approveRefundForChat(SupportChatSession $session, User $reviewer, int $amountCents, ?string $note = null): AfterSalesRequest
    {
        abort_unless(AdminAccess::canAction('after_sales.refund', $reviewer), 403);
        $this->ensureOrderSession($session);

        return DB::transaction(function () use ($session, $reviewer, $amountCents, $note): AfterSalesRequest {
            $request = AfterSalesRequest::query()->create([
                'user_id' => $session->user_id,
                'order_id' => $session->order_id,
                'type' => 'refund',
                'status' => AfterSalesRequest::STATUS_RESOLVED,
                'subject' => '客服会话直接退款',
                'message' => $this->messageOrDefault($note, '客服会话中直接登记退款。'),
                'admin_note' => $this->operatorNote($reviewer, $note),
                'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                'refund_amount_cents' => $amountCents,
                'refund_status' => AfterSalesRequest::REFUND_APPROVED,
                'refund_requested_by_id' => $reviewer->id,
                'refund_requested_at' => now(),
                'refund_reviewed_by_id' => $reviewer->id,
                'refund_reviewed_at' => now(),
                'resolved_at' => now(),
            ]);

            app(WalletService::class)->refundAfterSalesRequest($request, $reviewer);

            $this->addSystemMessage($session, '已退款到钱包。');

            return $request;
        });
    }

    public function refundOrderToWallet(Order $order, User $reviewer, int $amountCents, string $reason, string $idempotencyKey): AfterSalesRequest
    {
        abort_unless(AdminAccess::canAction('after_sales.refund', $reviewer), 403);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 3000) {
            throw ValidationException::withMessages(['reason' => '请输入退款原因（不超过 3000 字）。']);
        }

        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => '无效的退款操作编号。']);
        }

        return DB::transaction(function () use ($order, $reviewer, $amountCents, $reason, $idempotencyKey): AfterSalesRequest {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = WalletTransaction::query()->where('idempotency_key', 'direct-refund:'.$idempotencyKey)->lockForUpdate()->first();

            if ($existing) {
                if ((int) $existing->order_id !== (int) $order->id || (int) $existing->amount_cents !== $amountCents
                    || (int) $existing->created_by_user_id !== (int) $reviewer->id
                    || $existing->afterSalesRequest?->message !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => '操作编号已用于其他退款。']);
                }

                return $existing->afterSalesRequest;
            }

            $request = AfterSalesRequest::query()->create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => 'wallet_refund',
                'status' => AfterSalesRequest::STATUS_OPEN,
                'subject' => '后台直接退款到钱包',
                'message' => $reason,
                'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                'refund_amount_cents' => $amountCents,
                'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
                'refund_requested_by_id' => $reviewer->id,
                'refund_requested_at' => now(),
            ]);
            $this->approveRefundRequest($request, $reviewer, $reason);
            $transaction = WalletTransaction::query()->where('after_sales_request_id', $request->id)->firstOrFail();
            $transaction->update(['idempotency_key' => 'direct-refund:'.$idempotencyKey]);
            app(AdminActivityLogger::class)->log(
                'order_refunded_to_wallet',
                $order,
                sprintf('后台订单退款到钱包：订单 %s，退款金额 %s。原因：%s', $order->order_number, Money::format($amountCents), $reason),
                [
                    'order_number' => $order->order_number,
                    'customer_user_id' => $order->user_id,
                    'wallet_transaction_id' => $transaction->id,
                    'after_sales_request_id' => $request->id,
                    'amount_cents' => (int) $transaction->amount_cents,
                    'balance_before_cents' => (int) $transaction->balance_after_cents - (int) $transaction->amount_cents,
                    'balance_after_cents' => (int) $transaction->balance_after_cents,
                    'reason' => $reason,
                ],
                $reviewer,
            );

            return $request->fresh();
        });
    }

    public function approveCouponRequest(AfterSalesRequest $request, User $issuer, ?string $note = null): UserCoupon
    {
        if (! $request->user || ! $request->coupon_id) {
            throw ValidationException::withMessages(['coupon_id' => '审批记录缺少用户或优惠码，无法发放。']);
        }

        $coupon = Coupon::query()->findOrFail($request->coupon_id);

        $request->update([
            'status' => AfterSalesRequest::STATUS_RESOLVED,
            'resolution_type' => AfterSalesRequest::RESOLUTION_COUPON,
            'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($issuer, $note)),
            'resolved_at' => now(),
        ]);

        return app(CouponService::class)->issueToUser(
            $coupon,
            $request->user,
            UserCoupon::SOURCE_AFTER_SALES,
            $issuer,
            $request->id,
            $note,
        );
    }

    public function requestCouponForAfterSales(AfterSalesRequest $request, Coupon $coupon, User $requester, ?string $note = null): void
    {
        if (! $request->user) {
            throw ValidationException::withMessages(['user_id' => '该售后需求没有绑定用户，无法申请发券。']);
        }

        $request->update([
            'status' => AfterSalesRequest::STATUS_CONTACTING,
            'resolution_type' => AfterSalesRequest::RESOLUTION_COUPON,
            'coupon_id' => $coupon->id,
            'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($requester, $note)),
            'resolved_at' => null,
        ]);
    }

    public function rejectCouponRequest(AfterSalesRequest $request, User $reviewer, ?string $note = null): void
    {
        $request->update([
            'status' => AfterSalesRequest::STATUS_CLOSED,
            'resolution_type' => AfterSalesRequest::RESOLUTION_COUPON,
            'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($reviewer, $note)),
        ]);
    }

    public function approveRefundRequest(AfterSalesRequest $request, User $reviewer, ?string $note = null): void
    {
        abort_unless(AdminAccess::canAction('after_sales.refund', $reviewer), 403);

        DB::transaction(function () use ($request, $reviewer, $note): void {
            $request = AfterSalesRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->refund_status === AfterSalesRequest::REFUND_APPROVED) {
                return;
            }

            if ($request->refund_status !== AfterSalesRequest::REFUND_REQUESTED || ! $request->order_id
                || in_array($request->status, [AfterSalesRequest::STATUS_RESOLVED, AfterSalesRequest::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages(['refund_status' => '仅待审批退款申请可以审批。']);
            }

            $request->update([
                'status' => AfterSalesRequest::STATUS_RESOLVED,
                'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
                'refund_status' => AfterSalesRequest::REFUND_APPROVED,
                'refund_reviewed_by_id' => $reviewer->id,
                'refund_reviewed_at' => now(),
                'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($reviewer, $note)),
                'resolved_at' => now(),
            ]);

            app(WalletService::class)->refundAfterSalesRequest($request, $reviewer);
        });
    }

    public function rejectRefundRequest(AfterSalesRequest $request, User $reviewer, ?string $note = null): void
    {
        abort_unless(AdminAccess::canAction('after_sales.refund', $reviewer), 403);

        if (trim((string) $note) === '') {
            throw ValidationException::withMessages(['admin_note' => '请输入驳回原因。']);
        }

        DB::transaction(function () use ($request, $reviewer, $note): void {
            $request = AfterSalesRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->refund_status !== AfterSalesRequest::REFUND_REQUESTED) {
                throw ValidationException::withMessages(['refund_status' => '仅待审批退款申请可以驳回。']);
            }

            $request->update([
                'status' => AfterSalesRequest::STATUS_CONTACTING,
                'refund_status' => AfterSalesRequest::REFUND_REJECTED,
                'refund_reviewed_by_id' => $reviewer->id,
                'refund_reviewed_at' => now(),
                'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($reviewer, $note)),
            ]);
        });
    }

    private function ensureCustomerSession(SupportChatSession $session): void
    {
        $session->loadMissing('user');

        if (! $session->user) {
            throw ValidationException::withMessages(['user_id' => '该会话没有绑定用户，无法处理账户优惠码。']);
        }
    }

    private function ensureOrderSession(SupportChatSession $session): void
    {
        $session->loadMissing(['user', 'order']);

        if (! $session->user || ! $session->order || (int) $session->user_id !== (int) $session->order->user_id) {
            throw ValidationException::withMessages(['order_id' => '退款需要绑定用户和订单。']);
        }
    }

    private function addSystemMessage(SupportChatSession $session, string $body): void
    {
        SupportChatMessage::query()->create([
            'support_chat_session_id' => $session->id,
            'sender_type' => SupportChatMessage::SENDER_SYSTEM,
            'body' => $body,
        ]);

        $session->forceFill(['last_message_at' => now()])->save();
    }

    private function messageOrDefault(?string $message, string $default): string
    {
        $message = trim((string) $message);

        return $message === '' ? $default : $message;
    }

    private function operatorNote(User $operator, ?string $note = null): string
    {
        $note = trim((string) $note);
        $prefix = sprintf('%s：%s', $operator->displayName(), now()->format('Y-m-d H:i'));

        return $note === '' ? $prefix : $prefix.PHP_EOL.$note;
    }

    private function appendNote(?string $oldNote, ?string $newNote): ?string
    {
        $newNote = trim((string) $newNote);

        if ($newNote === '') {
            return $oldNote;
        }

        $oldNote = trim((string) $oldNote);

        return $oldNote === '' ? $newNote : $oldNote.PHP_EOL.PHP_EOL.$newNote;
    }
}
