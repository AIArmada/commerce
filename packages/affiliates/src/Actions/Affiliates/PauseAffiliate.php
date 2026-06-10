<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Paused;
use Lorisleiva\Actions\Concerns\AsAction;

final class PauseAffiliate
{
    use AsAction;

    public function handle(Affiliate $affiliate): Affiliate
    {
        $affiliate->status = new Paused($affiliate);
        $affiliate->paused_at = now();
        $affiliate->save();

        return $affiliate;
    }
}
