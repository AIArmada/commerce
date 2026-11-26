<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Data\CountryStateSeedData;
use AIArmada\Addressing\Models\AddressCountry;

interface CountryStateSeeder
{
    public function execute(AddressCountry $country, CountryStateSeedData $data): void;
}
