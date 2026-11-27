<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Contracts;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;

interface ZoneRateReader
{
    /**
     * @param  array<PackageData>  $packages
     * @return list<RateQuoteData>
     */
    public function quote(AddressData $destination, array $packages, int $cartTotal, int $itemCount, string $currency, ?string $ownerId = null, ?string $ownerType = null): array;

    public function isServiceable(AddressData $address, ?string $ownerId = null, ?string $ownerType = null): bool;
}
