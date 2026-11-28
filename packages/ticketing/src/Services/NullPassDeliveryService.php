<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;

final class NullPassDeliveryService implements PassDeliveryServiceInterface
{
    public function deliver(string $passId): void {}
}
