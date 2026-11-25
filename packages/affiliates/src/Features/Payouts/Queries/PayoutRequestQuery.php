<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\PayoutStatus;

final class PayoutRequestQuery
{
    /** @param array<string, mixed>|null $methodDetails */
    public static function fromPayout(AffiliatePayout $payout, ?array $methodDetails = null): PayoutRequest
    {
        $operation = $payout->operation;
        $affiliate = $payout->payee;
        $hasAffiliate = $affiliate instanceof Affiliate;

        if ($methodDetails === null && $affiliate instanceof Affiliate) {
            $methodDetails = AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->getKey())->where('is_default', true)->orderBy('id')->first()?->details;
        }

        return new PayoutRequest(
            id: (string) $payout->getKey(),
            totalMinor: (int) $payout->total_minor,
            currency: (string) $payout->currency,
            status: PayoutStatus::normalize($payout->status),
            operationId: $operation?->id,
            operationStatus: $operation?->status,
            externalReference: $payout->external_reference ?? $operation?->provider_reference,
            hasAffiliate: $hasAffiliate,
            methodDetails: $methodDetails ?? [],
        );
    }
}
