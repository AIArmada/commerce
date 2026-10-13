<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReferralAffiliateAwardPolicy implements AffiliateAwardPolicy
{
    public function __construct(private readonly ReferralProgramGuard $guard, private readonly ReferralAffiliateResolver $affiliateResolver) {}

    public function decide(Affiliate $recipient, AffiliateAwardProposal $proposal): AffiliateAwardDecision
    {
        if (preg_match('/^referrals:award:([0-9a-f-]{36}):(referrer|referee)$/', $proposal->awardKey, $matches) !== 1) {
            return AffiliateAwardDecision::deny('Referral award key is malformed.');
        }

        $inviteId = $matches[1];
        $side = ReferralRewardSide::tryFrom($matches[2]);

        if ($side === null) {
            return AffiliateAwardDecision::deny('Referral award side is invalid.');
        }

        return DB::transaction(function () use ($recipient, $proposal, $inviteId, $side): AffiliateAwardDecision {
            try {
                $reward = $this->guard->rewardForInviteSide($inviteId, $side, true);
            } catch (Throwable) {
                return AffiliateAwardDecision::deny('No pending referral reward matches this award.');
            }

            if ($reward->status !== ReferralRewardStatus::Pending
                || $reward->driver !== 'affiliate_cash'
                || $reward->amount_minor === null
                || $reward->currency === null
                || $proposal->amountMinor !== $reward->amount_minor
                || $proposal->currency !== $reward->currency
                || $proposal->sourceRef !== $inviteId) {
                return AffiliateAwardDecision::deny('Referral award amount, currency, source, or status does not match the frozen reward.');
            }

            $invite = $reward->invite;

            if ($invite->qualification_business_key === null
                || $proposal->externalReference !== $invite->qualification_business_key) {
                return AffiliateAwardDecision::deny('Referral award reference does not match the qualification business key.');
            }

            $expectedAffiliate = $this->affiliateResolver->resolve($reward);

            if (! $expectedAffiliate instanceof Affiliate
                || $expectedAffiliate->getMorphClass() !== $recipient->getMorphClass()
                || (string) $expectedAffiliate->getKey() !== (string) $recipient->getKey()) {
                return AffiliateAwardDecision::deny('Affiliate recipient does not match the referral beneficiary.');
            }

            return AffiliateAwardDecision::allow($reward->amount_minor, $reward->amount_minor);
        });
    }
}
