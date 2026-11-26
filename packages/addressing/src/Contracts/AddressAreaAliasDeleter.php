<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;

interface AddressAreaAliasDeleter
{
    public function execute(AddressArea $area, AddressAreaName $alias): void;
}
