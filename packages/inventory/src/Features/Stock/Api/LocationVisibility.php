<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface LocationVisibility
{
    public function isVisible(string $locationId): bool;
}
