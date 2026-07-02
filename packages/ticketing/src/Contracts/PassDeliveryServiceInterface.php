<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Models\Pass;

interface PassDeliveryServiceInterface
{
    public function deliver(Pass $pass): void;
}
