<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an affiliate is activated (status changed to Active).
 */
final readonly class AffiliateActivated
{
    use Dispatchable;

    public function __construct(
        public AffiliateLifecycleReference $affiliate,
    ) {}
}
