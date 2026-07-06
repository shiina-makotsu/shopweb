<?php

namespace App\Services;

use App\Models\EventRewardGrant;
use App\Models\ReferralRewardRule;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReferralRewardService
{
    public function applyForNewReferral(User $inviter, User $invitee): void
    {
        $this->grantForEvent($inviter, ReferralRewardRule::EVENT_REFERRAL_REGISTERED, $invitee, [
            'note' => '邀请 '.$invitee->displayName().' 注册奖励',
        ]);
    }

    public function grantForEvent(User $user, string $event, ?Model $subject = null, array $context = []): void
    {
        ReferralRewardRule::query()
            ->active()
            ->with('coupon')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ReferralRewardRule $rule): bool => $rule->appliesToEvent($event, $context))
            ->each(function (ReferralRewardRule $rule) use ($user, $event, $subject, $context): void {
                DB::transaction(function () use ($rule, $user, $event, $subject, $context): void {
                    $grant = EventRewardGrant::query()->firstOrCreate([
                        'referral_reward_rule_id' => $rule->id,
                        'user_id' => $user->id,
                        'event' => $event,
                        'subject_type' => $subject ? $subject::class : null,
                        'subject_id' => $subject?->getKey(),
                    ]);

                    if (! $grant->wasRecentlyCreated) {
                        return;
                    }

                    $note = (string) ($context['note'] ?? (ReferralRewardRule::eventLabel($event).'奖励'));
                    $couponSource = $event === ReferralRewardRule::EVENT_REFERRAL_REGISTERED
                        ? UserCoupon::SOURCE_REFERRAL
                        : UserCoupon::SOURCE_EVENT_REWARD;
                    $walletSource = $event === ReferralRewardRule::EVENT_REFERRAL_REGISTERED
                        ? WalletTransaction::SOURCE_REFERRAL
                        : WalletTransaction::SOURCE_EVENT_REWARD;

                    if ($rule->coupon) {
                        try {
                            app(CouponService::class)->issueToUser($rule->coupon, $user, $couponSource, note: $note);
                        } catch (Throwable) {
                            // A duplicate or exhausted coupon must not block the event flow.
                        }
                    }

                    if ((int) $rule->wallet_amount_cents > 0) {
                        app(WalletService::class)->credit($user, (int) $rule->wallet_amount_cents, $walletSource, $note);
                    }
                });
            });
    }
}
