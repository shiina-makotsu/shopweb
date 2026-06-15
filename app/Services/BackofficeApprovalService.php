<?php

namespace App\Services;

use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Validation\ValidationException;

class BackofficeApprovalService
{
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
        $this->ensureOrderSession($session);

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
        $this->ensureOrderSession($session);

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

        $this->addSystemMessage($session, '已登记直接退款。');

        return $request;
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
        $request->update([
            'status' => AfterSalesRequest::STATUS_RESOLVED,
            'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
            'refund_status' => AfterSalesRequest::REFUND_APPROVED,
            'refund_reviewed_by_id' => $reviewer->id,
            'refund_reviewed_at' => now(),
            'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($reviewer, $note)),
            'resolved_at' => now(),
        ]);
    }

    public function rejectRefundRequest(AfterSalesRequest $request, User $reviewer, ?string $note = null): void
    {
        $request->update([
            'status' => AfterSalesRequest::STATUS_CONTACTING,
            'refund_status' => AfterSalesRequest::REFUND_REJECTED,
            'refund_reviewed_by_id' => $reviewer->id,
            'refund_reviewed_at' => now(),
            'admin_note' => $this->appendNote($request->admin_note, $this->operatorNote($reviewer, $note)),
        ]);
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

        if (! $session->user || ! $session->order) {
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
