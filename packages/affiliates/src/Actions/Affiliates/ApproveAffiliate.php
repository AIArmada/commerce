<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Approve a pending affiliate.
 */
final class ApproveAffiliate
{
    use AsAction;

    /**
     * Approve a pending affiliate and set them to active status.
     */
    public function handle(Affiliate $affiliate): Affiliate
    {
        if ($affiliate->status === AffiliateStatus::Active) {
            return $affiliate;
        }

        $affiliate->status = AffiliateStatus::Active;
        $affiliate->activated_at = now();
        $affiliate->save();

        return $affiliate;
    }
}
