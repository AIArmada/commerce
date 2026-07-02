<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use AIArmada\Ticketing\Models\Pass;

final class NullPassDeliveryService implements PassDeliveryServiceInterface
{
    public function deliver(Pass $pass): void {}
}
