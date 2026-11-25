<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a new affiliate is created.
 */
final readonly class AffiliateCreated
{
    use Dispatchable;

    public function __construct(
        public AffiliateLifecycleReference $affiliate,
    ) {}
}
