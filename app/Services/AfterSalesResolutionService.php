<?php

namespace App\Services;

use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\User;
use App\Models\UserCoupon;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AfterSalesResolutionService
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly BackofficeApprovalService $approvals,
        private readonly WalletService $wallets,
    ) {}

    /** @param array<string, mixed> $data */
    public function resolve(AfterSalesRequest $request, array $data, User $actor): void
    {
        DB::transaction(function () use ($request, $data, $actor): void {
            $request = AfterSalesRequest::query()
                ->with(['user', 'order'])
                ->lockForUpdate()
                ->findOrFail($request->id);
            $resolutionType = $this->resolutionType($data['resolution_type'] ?? null, $actor);
            $note = trim((string) ($data['admin_note'] ?? ''));

            if ($note === '') {
                throw ValidationException::withMessages(['admin_note' => '请输入处理留言。']);
            }

            $updates = [
                'status' => AfterSalesRequest::STATUS_RESOLVED,
                'resolution_type' => $resolutionType,
                'admin_note' => $request->mergedAdminNote($note),
                'resolved_at' => now(),
            ];

            if ($resolutionType === AfterSalesRequest::RESOLUTION_REFUND) {
                $amountCents = max(0, (int) ($data['refund_amount_cents'] ?? 0));

                if ($amountCents <= 0) {
                    throw ValidationException::withMessages(['refund_amount_cents' => '退款金额必须大于 0。']);
                }

                $updates += [
                    'refund_amount_cents' => $amountCents,
                    'refund_status' => AfterSalesRequest::REFUND_APPROVED,
                    'refund_reviewed_by_id' => $actor->id,
                    'refund_reviewed_at' => now(),
                ];
            } elseif ($resolutionType === AfterSalesRequest::RESOLUTION_COUPON) {
                $updates['coupon_id'] = filled($data['coupon_id'] ?? null) ? (int) $data['coupon_id'] : null;
            }

            $request->update($updates);

            if ($resolutionType === AfterSalesRequest::RESOLUTION_COUPON && $request->coupon_id && $request->user) {
                $coupon = Coupon::query()->find($request->coupon_id);

                if ($coupon && AdminAccess::canAction('coupons.issue', $actor)) {
                    $this->coupons->issueToUser(
                        $coupon,
                        $request->user,
                        UserCoupon::SOURCE_AFTER_SALES,
                        $actor,
                        $request->id,
                        '售后补偿',
                    );
                } elseif ($coupon) {
                    $this->approvals->requestCouponForAfterSales($request, $coupon, $actor, $note);
                }
            }

            if ($resolutionType === AfterSalesRequest::RESOLUTION_REFUND && $request->order) {
                $this->wallets->refundOrderPayment(
                    $request->order,
                    (int) $updates['refund_amount_cents'],
                    $actor,
                    '售后退款退回钱包',
                );
            }
        });
    }

    private function resolutionType(mixed $value, User $actor): string
    {
        $type = in_array($value, [
            AfterSalesRequest::RESOLUTION_REFUND,
            AfterSalesRequest::RESOLUTION_COUPON,
            AfterSalesRequest::RESOLUTION_MESSAGE,
        ], true) ? (string) $value : AfterSalesRequest::RESOLUTION_MESSAGE;

        return $type === AfterSalesRequest::RESOLUTION_REFUND && ! AdminAccess::canAction('after_sales.refund', $actor)
            ? AfterSalesRequest::RESOLUTION_MESSAGE
            : $type;
    }
}
