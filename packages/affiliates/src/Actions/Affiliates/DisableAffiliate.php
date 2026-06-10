<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
use Lorisleiva\Actions\Concerns\AsAction;

final class DisableAffiliate
{
    use AsAction;

    public function handle(Affiliate $affiliate): Affiliate
    {
        $affiliate->status = new Disabled($affiliate);
        $affiliate->deactivated_at = now();
        $affiliate->save();

        return $affiliate;
    }
}
