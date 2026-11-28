<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Events;

use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a customer's segment membership changes.
 */
final class CustomerSegmentChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $action  Either 'added' or 'removed'
     */
    public function __construct(
        public Customer $customer,
        public Segment $segment,
        public string $action,
    ) {}
}
