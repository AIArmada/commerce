<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;

final class OpenApprovalPolicy implements QualifiesForOpenApproval
{
    public function qualifies(AffiliateConversion $conversion): bool
    {
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

        $attribution = $conversion->attribution;

        if (! $attribution || (string) $attribution->affiliate_id !== (string) $affiliate->getKey()) {
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
        return ! $affiliate->fraudSignals()
            ->whereIn('status', [FraudSignalStatus::Detected, FraudSignalStatus::Confirmed])
            ->exists();
    }
}
