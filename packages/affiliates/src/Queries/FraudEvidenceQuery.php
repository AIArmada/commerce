<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Queries;

use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class FraudEvidenceQuery
{
    public function click(string $affiliateId, ?string $ipAddress, string $fingerprint, ?int $clickCount): ClickFraudEvidence
    {
        $now = CarbonImmutable::now();
        $fingerprintCount = null;
        $last = null;

        if (config('affiliates.tracking.fingerprint.enabled', false)) {
            $fingerprintCount = AffiliateTouchpoint::query()
                ->where('affiliate_id', $affiliateId)
                ->where('fingerprint', $fingerprint)
                ->where('touched_at', '>=', $now->subDay())
                ->count();
        }

        if ($ipAddress !== null && config('affiliates.fraud.anomaly.geo.enabled', false)) {
            $last = AffiliateTouchpoint::query()
                ->where('affiliate_id', $affiliateId)
                ->where('ip_address', '!=', $ipAddress)
                ->whereNotNull('touched_at')
                ->latest('touched_at')
                ->first(['ip_address', 'touched_at']);
        }

        return new ClickFraudEvidence(
            $ipAddress,
            $fingerprint,
            $clickCount,
            max(1, (int) config('affiliates.fraud.velocity.max_clicks_per_hour', 100)),
            $fingerprintCount,
            max(1, (int) config('affiliates.tracking.fingerprint.threshold', 5)),
            $last?->ip_address,
            $last?->touched_at?->diffInMinutes($now),
        );
    }

    public function conversion(AffiliateConversion $conversion): ConversionFraudEvidence
    {
        $affiliate = $conversion->affiliate;
        $date = CarbonImmutable::today();
        $actor = $conversion->actor_user_id;
        $userModel = config('auth.providers.users.model');
        $ownerType = $affiliate?->owner_type;
        $ownerId = $affiliate?->owner_id;
        $selfReferral = (bool) config('affiliates.tracking.block_self_referral', false)
            && $ownerId !== null && $actor !== null && (string) $ownerId === (string) $actor
            && is_string($userModel) && is_a($userModel, Model::class, true)
            && ($ownerType === $userModel || $ownerType === (new $userModel)->getMorphClass());

        return new ConversionFraudEvidence(
            $affiliate instanceof Affiliate ? $affiliate->conversions()->where('occurred_at', '>=', $date)->where('occurred_at', '<', $date->addDay())->count() : 0,
            max(1, (int) config('affiliates.fraud.velocity.max_conversions_per_day', 50)),
            $date->toDateString(),
            $selfReferral,
            $ownerId,
            $actor === null ? null : (string) $actor,
            $conversion->attribution?->first_seen_at?->toImmutable(),
            $conversion->occurred_at?->toImmutable(),
            max(0, (int) config('affiliates.fraud.anomaly.conversion_time.min_seconds', 5)),
        );
    }
}
