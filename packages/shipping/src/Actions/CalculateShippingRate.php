<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Actions;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Calculate shipping rate for a package.
 */
final class CalculateShippingRate
{
    use AsAction;

    public function __construct(
        private readonly ShippingManager $shippingManager,
    ) {}

    /**
     * Calculate shipping rates for packages.
     *
     * @param  array<\AIArmada\Shipping\Data\PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function handle(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        ?string $carrier = null,
        array $options = []
    ): Collection {
        if ($carrier !== null) {
            $driver = $this->shippingManager->driver($carrier);

            return $driver->getRates($origin, $destination, $packages, $options);
        }

        // Get rates from all configured carriers
        $rates = collect();
        $carriers = config('shipping.carriers', []);

        foreach (array_keys($carriers) as $carrierName) {
            try {
                $driver = $this->shippingManager->driver($carrierName);
                $carrierRates = $driver->getRates($origin, $destination, $packages, $options);
                $rates = $rates->merge($carrierRates);
            } catch (Throwable) {
                // Skip carriers that fail
                continue;
            }
        }

        return $rates;
    }
}
