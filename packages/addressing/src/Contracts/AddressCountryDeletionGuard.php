<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressCountry;

interface AddressCountryDeletionGuard
{
    public function handle(AddressCountry $country): void;
}
