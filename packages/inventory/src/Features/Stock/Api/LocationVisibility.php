<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface LocationVisibility
{
    public function isVisible(string $locationId): bool;

    public function isWritable(string $locationId): bool;

    /** @return iterable<array{id: string, name: string}> */
    public function activeLocations(bool $exactOwner = false): iterable;
}
