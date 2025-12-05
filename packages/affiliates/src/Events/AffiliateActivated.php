<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an affiliate is activated (status changed to Active).
 */
final class AffiliateActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Affiliate $affiliate,
    ) {}
}
