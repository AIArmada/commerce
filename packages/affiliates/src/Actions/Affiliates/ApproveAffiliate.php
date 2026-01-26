<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
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
        if ($affiliate->status->equals(Active::class)) {
            return $affiliate;
        }

        $affiliate->status = new Active($affiliate);
        $affiliate->activated_at = now();
        $affiliate->save();

        return $affiliate;
    }
}
