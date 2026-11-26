<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class AffiliateTierUpgraded
{
    use Dispatchable;

    public function __construct(
        public string $affiliateId,
        public string $programId,
        public ?string $fromTierId,
        public string $toTierId
    ) {}
}
