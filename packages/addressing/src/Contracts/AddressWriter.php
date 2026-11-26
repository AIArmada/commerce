<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\Address;

interface AddressWriter
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|null> $areaAssignments
     * @param class-string<Address>|null $modelClass
     */
    public function execute(array $data, array $areaAssignments, ?Address $address = null, ?string $modelClass = null): Address;
}
