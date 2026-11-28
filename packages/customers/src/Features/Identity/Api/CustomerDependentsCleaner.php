<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Api;

interface CustomerDependentsCleaner
{
    public function cleanup(string $customerId): void;
}
