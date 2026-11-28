<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

use AIArmada\Orders\Data\ReturnedPackageUnit;

interface PackageReturnVerifier
{
    public function receivedUnit(string $orderId, string $authorizationId): ReturnedPackageUnit;
}
