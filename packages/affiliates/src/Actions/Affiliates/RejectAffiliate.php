<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reject a pending affiliate.
 */
final class RejectAffiliate
{
    use AsAction;

    /**
     * Reject a pending affiliate and set them to disabled status.
     */
    public function handle(Affiliate $affiliate): Affiliate
    {
        $affiliate->status = AffiliateStatus::Disabled;
        $affiliate->save();

        return $affiliate;
    }
}
