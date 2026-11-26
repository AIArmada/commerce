<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Queries;

use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Attribution\Api\TouchpointReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspection;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final readonly class FraudEvidenceQuery
{
    public function __construct(private TouchpointReader $touchpoints, private AttributionConversionReader $attributions, private ConversionStatisticsReader $statistics) {}

    public function click(string $affiliateId, ?string $ipAddress, string $fingerprint, ?int $clickCount): ClickFraudEvidence
    {
        $now = CarbonImmutable::now();
        $fingerprintCount = null;
        $last = null;

        if (config('affiliates.tracking.fingerprint.enabled', false)) {
            $fingerprintCount = $this->touchpoints->fingerprintCount($affiliateId, $fingerprint, $now->subDay());
        }

        if ($ipAddress !== null && config('affiliates.fraud.anomaly.geo.enabled', false)) {
            $last = $this->touchpoints->latestOtherIp($affiliateId, $ipAddress);
        }

        return new ClickFraudEvidence(
            $ipAddress,
            $fingerprint,
            $clickCount,
            max(0, (int) config('affiliates.fraud.velocity.max_clicks_per_hour', 100)),
            $fingerprintCount,
            max(1, (int) config('affiliates.tracking.fingerprint.threshold', 5)),
            $last['ip_address'] ?? null,
            ($last['touched_at'] ?? null)?->diffInMinutes($now),
        );
    }

    public function conversion(ConversionInspection $conversion): ConversionFraudEvidence
    {
        $affiliate = Affiliate::query()->forOwner(includeGlobal: true)->find($conversion->affiliateId);
        $date = CarbonImmutable::today();
        $actor = $conversion->actorUserId;
        $userModel = config('auth.providers.users.model');
        $ownerType = $affiliate?->owner_type;
        $ownerId = $affiliate?->owner_id;
        $selfReferral = (bool) config('affiliates.tracking.block_self_referral', false)
            && $ownerId !== null && $actor !== null && (string) $ownerId === (string) $actor
            && is_string($userModel) && is_a($userModel, Model::class, true)
            && ($ownerType === $userModel || $ownerType === (new $userModel)->getMorphClass());

        $attribution = $conversion->attributionId === null ? null : $this->attributions->evidence(
            $conversion->attributionId,
            $conversion->ownerType,
            $conversion->ownerId,
            $conversion->connectionIdentity,
        );

        return new ConversionFraudEvidence(
            $affiliate instanceof Affiliate ? $this->statistics->count([(string) $affiliate->getKey()], spl_object_id($affiliate->getConnection()), $date, $date->addDay()) : 0,
            max(0, (int) config('affiliates.fraud.velocity.max_conversions_per_day', 50)),
            $date->toDateString(),
            $selfReferral,
            $ownerId,
            $actor === null ? null : (string) $actor,
            $attribution?->firstSeenAt,
            $conversion->occurredAt,
            max(0, (int) config('affiliates.fraud.anomaly.conversion_time.min_seconds', 5)),
        );
    }
}
