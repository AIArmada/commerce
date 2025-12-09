<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AffiliateProgramLeft
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly AffiliateProgram $program
    ) {}
}
