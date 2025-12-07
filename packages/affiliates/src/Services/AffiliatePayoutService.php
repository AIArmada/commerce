<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Models\AffiliatePayout;

/**
 * Service for affiliate payout operations.
 *
 * This service now delegates to individual Actions for cleaner architecture.
 *
 * @deprecated Use the individual Actions directly:
 *             - CreatePayout::run($conversionIds, $attributes)
 *             - UpdatePayoutStatus::run($payout, $status, $notes, $metadata)
 */
final class AffiliatePayoutService
{
    public function __construct(
        private readonly CreatePayout $createPayout,
        private readonly UpdatePayoutStatus $updatePayoutStatus,
    ) {
    }

    /**
     * Create a payout from the given conversion IDs.
     *
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     *
     * @deprecated Use CreatePayout::run($conversionIds, $attributes) instead
     */
    public function createPayout(array $conversionIds, array $attributes = []): AffiliatePayout
    {
        return $this->createPayout->handle($conversionIds, $attributes);
    }

    /**
     * Update the status of a payout.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @deprecated Use UpdatePayoutStatus::run($payout, $status, $notes, $metadata) instead
     */
    public function updateStatus(AffiliatePayout $payout, string $status, ?string $notes = null, array $metadata = []): AffiliatePayout
    {
        return $this->updatePayoutStatus->handle($payout, $status, $notes, $metadata);
    }
}
