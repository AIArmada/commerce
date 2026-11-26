<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class AffiliateProgramLeft
{
    use Dispatchable;

    public function __construct(
        public string $affiliateId,
        public string $programId,
        public string $membershipId
    ) {}
}
