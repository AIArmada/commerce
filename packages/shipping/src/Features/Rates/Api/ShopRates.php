<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Api;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use Illuminate\Support\Collection;

interface ShopRates
{
    /**
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function getAllRates(AddressData $origin, AddressData $destination, array $packages, array $options = []): Collection;

    /**
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     */
    public function getBestRate(AddressData $origin, AddressData $destination, array $packages, array $options = []): ?RateQuoteData;
}
