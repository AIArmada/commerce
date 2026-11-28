<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Features\Passes\Models\Pass;

interface PassDeliveryServiceInterface
{
    public function deliver(Pass $pass): void;
}
