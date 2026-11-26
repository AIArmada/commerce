<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressArea;

interface AddressAreaDeletionGuard
{
    public function handle(AddressArea $addressArea): void;
}
