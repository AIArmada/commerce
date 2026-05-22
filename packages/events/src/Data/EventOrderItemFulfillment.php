<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Occurrence;
use InvalidArgumentException;

final class EventOrderItemFulfillment
{
    /**
     * @param  array<int, array<string, mixed>>  $participants
     */
    public function __construct(
        public readonly Occurrence $occurrence,
        public readonly array $participants,
        public readonly ?Customer $purchaser = null,
    ) {
        if ($participants === []) {
            throw new InvalidArgumentException('Event order item fulfillment requires at least one participant.');
        }
    }
}
