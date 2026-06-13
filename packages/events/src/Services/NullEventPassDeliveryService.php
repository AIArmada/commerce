<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventPassDeliveryService;
use AIArmada\Events\Models\EventPass;

final class NullEventPassDeliveryService implements EventPassDeliveryService
{
    public function deliver(EventPass $pass): void {}
}
