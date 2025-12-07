<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Exceptions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(
        public readonly ShipmentStatus $from,
        public readonly ShipmentStatus $to
    ) {
        parent::__construct(
            "Cannot transition shipment from [{$from->value}] to [{$to->value}]."
        );
    }
}
