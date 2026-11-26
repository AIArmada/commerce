<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\States\PayoutStatus;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;

final class PayoutRequestQuery
{
    /** @param array<string, mixed>|null $methodDetails */
    public static function fromPayout(AffiliatePayout $payout, ?array $methodDetails = null): PayoutRequest
    {
        $operation = PayoutOperationQuery::forPayout($payout)->first();
        $profiles = app(AffiliateProfileReader::class);
        $affiliate = $profiles->matchesMorphType($payout->payee_type) && $payout->payee_id !== null
            ? $profiles->find($payout->payee_id, spl_object_id($payout->getConnection()))
            : null;
        $hasAffiliate = $affiliate !== null;

        if ($methodDetails === null && $affiliate !== null) {
            $methodDetails = AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->reference->id)->where('is_default', true)->orderBy('id')->first()?->details;
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
