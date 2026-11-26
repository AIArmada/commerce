<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Queries;

use AIArmada\Affiliates\Data\ClickFraudEvidence;
use AIArmada\Affiliates\Data\ConversionFraudEvidence;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Attribution\Api\TouchpointReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspection;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
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
        $affiliate = app(AffiliateReferenceReader::class)->ownerReference($conversion->affiliateId, $conversion->connectionIdentity, includeGlobal: true);
        $date = CarbonImmutable::today();
        $actor = $conversion->actorUserId;
        $userModel = config('auth.providers.users.model');
        $ownerType = $affiliate?->ownerType;
        $ownerId = $affiliate?->ownerId;
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
            $affiliate !== null ? $this->statistics->count([$affiliate->id], $conversion->connectionIdentity, $date, $date->addDay()) : 0,
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
