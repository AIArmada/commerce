<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;

final readonly class OpenApprovalPolicy implements QualifiesForOpenApproval
{
    public function __construct(private FraudApi $fraud, private AttributionConversionReader $attributions) {}

    public function qualifies(string $conversionId): bool
    {
        $conversion = AffiliateConversion::query()->with('affiliate')->find($conversionId);

        if (! $conversion instanceof AffiliateConversion) {
            return false;
        }

        $affiliate = $conversion->affiliate;

        if (! $affiliate) {
            return false;
        }

        if (RegistrationApprovalMode::tryFrom((string) $affiliate->registration_approval_mode) !== RegistrationApprovalMode::Open) {
            return false;
        }

        if (! $affiliate->status instanceof Pending) {
            return false;
        }

        $attribution = $conversion->affiliate_attribution_id === null ? null : $this->attributions->evidence(
            $conversion->affiliate_attribution_id,
            $conversion->owner_type,
            $conversion->owner_id,
            spl_object_id($conversion->getConnection()),
        );

        if ($attribution === null || $attribution->affiliateId !== (string) $affiliate->getKey()) {
            return false;
        }

        if ($conversion->status->equals(RejectedConversion::class, ReversedConversion::class)) {
            return false;
        }

        $minimum = (int) config('affiliates.registration.open_approval_min_commission_minor', 0);

        if ((int) $conversion->commission_minor <= $minimum) {
            return false;
        }

        // Unresolved signals block regardless of age: the risk profile's
        // 30-day window would let a stale Detected signal through. Never
        // re-run analyzeConversion here; it persists new signals on every
        // call, while record-time screening already ran (its rejections
        // arrive as Rejected conversions, excluded above).
        return ! $this->fraud->hasUnresolvedSignals((string) $affiliate->getKey());
    }
}
