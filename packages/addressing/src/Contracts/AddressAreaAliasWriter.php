<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;

interface AddressAreaAliasWriter
{
    /** @param array{name: mixed, name_type?: mixed, is_preferred?: mixed} $attributes */
    public function handle(AddressArea $area, array $attributes, ?AddressAreaName $record = null): AddressAreaName;
}
