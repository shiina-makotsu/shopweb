<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\EventRewardGrant;
use App\Models\ReferralRewardRule;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletTransaction;
use App\Support\Money;
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
            ->with('coupon.products')
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
                    $rewardRules = $rule->couponRulesForIssuance();
                    $couponIds = [];
                    $couponSnapshots = [];
                    $walletAmountCents = 0;
                    $errors = [];

                    if ($rewardRules !== []) {
                        try {
                            $issuedCoupons = app(GeneratedCouponRewardService::class)->issueToUserWithDetails(
                                $user,
                                $rewardRules,
                                $couponSource,
                                $note,
                                fn (array $rewardRule, int $ruleNumber, int $index, int $quantity): string => $this->eventRewardCouponName($rule, $rewardRule, $index, $quantity),
                                null,
                                'ER',
                            );

                            foreach ($issuedCoupons as $coupon) {
                                $couponIds[] = (int) $coupon->id;
                                $couponSnapshots[] = [
                                    'id' => (int) $coupon->id,
                                    'name' => (string) $coupon->name,
                                    'type' => (string) $coupon->type,
                                    'value' => (int) $coupon->value,
                                    'scope' => (string) $coupon->scope,
                                    'minimum_order_cents' => (int) $coupon->minimum_order_cents,
                                    'usage_limit' => (int) $coupon->usage_limit,
                                    'per_user_limit' => (int) $coupon->per_user_limit,
                                    'is_stackable' => (bool) $coupon->is_stackable,
                                    'ends_at' => $coupon->ends_at?->toIso8601String(),
                                ];
                            }
                        } catch (Throwable $exception) {
                            $errors[] = '优惠码发放失败：'.$exception->getMessage();
                        }
                    }

                    if ((int) $rule->wallet_amount_cents > 0) {
                        try {
                            app(WalletService::class)->credit($user, (int) $rule->wallet_amount_cents, $walletSource, $note);
                            $walletAmountCents = (int) $rule->wallet_amount_cents;
                        } catch (Throwable $exception) {
                            $errors[] = '钱包奖励发放失败：'.$exception->getMessage();
                        }
                    }

                    $hasDeliveredReward = $couponIds !== [] || $walletAmountCents > 0;
                    $status = $errors === []
                        ? EventRewardGrant::STATUS_COMPLETED
                        : ($hasDeliveredReward ? EventRewardGrant::STATUS_PARTIAL : EventRewardGrant::STATUS_FAILED);

                    $grant->forceFill([
                        'status' => $status,
                        'coupon_ids' => $couponIds,
                        'wallet_amount_cents' => $walletAmountCents,
                        'reward_snapshot' => [
                            'rule_name' => $rule->name,
                            'event' => $event,
                            'coupon_rules' => $rewardRules,
                            'issued_coupons' => $couponSnapshots,
                            'wallet_amount_cents' => $walletAmountCents,
                        ],
                        'error_message' => $errors !== [] ? implode("\n", $errors) : null,
                        'completed_at' => now(),
                    ])->save();
                });
            });
    }

    /**
     * @param  array<string, mixed>  $rewardRule
     */
    private function eventRewardCouponName(ReferralRewardRule $rule, array $rewardRule, int $index, int $quantity): string
    {
        $suffix = $quantity > 1 ? " {$index}/{$quantity}" : '';

        return trim($rule->name.' '.$this->rewardDiscountLabel($rewardRule).$suffix);
    }

    /**
     * @param  array<string, mixed>  $rewardRule
     */
    private function rewardDiscountLabel(array $rewardRule): string
    {
        if (($rewardRule['type'] ?? null) === Coupon::TYPE_PERCENT) {
            return max(1, min(100, (int) ($rewardRule['value'] ?? 0))).'%';
        }

        return Money::format(max(0, (int) ($rewardRule['value'] ?? 0)));
    }
}
