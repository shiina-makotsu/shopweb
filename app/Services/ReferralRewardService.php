<?php

namespace App\Services;

use App\Models\ReferralRewardRule;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReferralRewardService
{
    public function applyForNewReferral(User $inviter, User $invitee): void
    {
        ReferralRewardRule::query()
            ->active()
            ->with('coupon')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (ReferralRewardRule $rule) use ($inviter, $invitee): void {
                DB::transaction(function () use ($rule, $inviter, $invitee): void {
                    if ($rule->coupon) {
                        try {
                            app(CouponService::class)->issueToUser(
                                $rule->coupon,
                                $inviter,
                                UserCoupon::SOURCE_REFERRAL,
                                null,
                                null,
                                '邀请 '.$invitee->displayName().' 注册奖励',
                            );
                        } catch (Throwable) {
                            // A duplicate or exhausted coupon must not block registration.
                        }
                    }

                    if ((int) $rule->wallet_amount_cents > 0) {
                        app(WalletService::class)->credit(
                            $inviter,
                            (int) $rule->wallet_amount_cents,
                            WalletTransaction::SOURCE_REFERRAL,
                            '邀请 '.$invitee->displayName().' 注册奖励',
                        );
                    }
                });
            });
    }
}
