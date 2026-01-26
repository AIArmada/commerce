<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
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
        $affiliate->status = new Disabled($affiliate);
        $affiliate->save();

        return $affiliate;
    }
}
