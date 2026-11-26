<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionConversionReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspectionReader;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Pending;

final readonly class OpenApprovalPolicy implements QualifiesForOpenApproval
{
    public function __construct(private FraudApi $fraud, private AttributionConversionReader $attributions, private ConversionInspectionReader $conversions) {}

    public function qualifies(string $conversionId): bool
    {
        $conversion = $this->conversions->find($conversionId);

        if ($conversion === null) {
            return false;
        }

        $affiliate = Affiliate::query()->forOwner(includeGlobal: true)->find($conversion->affiliateId);

        if (! $affiliate) {
            return false;
        }

        if (RegistrationApprovalMode::tryFrom((string) $affiliate->registration_approval_mode) !== RegistrationApprovalMode::Open) {
            return false;
        }

        if (! $affiliate->status instanceof Pending) {
            return false;
        }

        $attribution = $conversion->attributionId === null ? null : $this->attributions->evidence(
            $conversion->attributionId,
            $conversion->ownerType,
            $conversion->ownerId,
            $conversion->connectionIdentity,
        );

        if ($attribution === null || $attribution->affiliateId !== (string) $affiliate->getKey()) {
            return false;
        }

        if (in_array($conversion->status, [ConversionStatus::Rejected->value, ConversionStatus::Reversed->value], true)) {
            return false;
        }

        $minimum = (int) config('affiliates.registration.open_approval_min_commission_minor', 0);

        if ($conversion->commissionMinor <= $minimum) {
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
