<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Actions;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Features\Rates\Api\RateAddress;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Features\Rates\Api\RatePackage;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Features\Rates\Api\RateQuote;
use AIArmada\Shipping\Features\Rates\Api\CalculateRates;
use AIArmada\Shipping\ShippingManager;
use Throwable;

/**
 * Calculate shipping rate for a package.
 */
final class CalculateShippingRate implements CalculateRates
{
    public function __construct(
        private readonly ShippingManager $shippingManager,
    ) {}

    /**
     * Calculate shipping rates for packages.
     *
     * @param  list<RatePackage>  $packages
     * @param  array<string, mixed>  $options
     * @return list<RateQuote>
     */
    public function handle(
        RateAddress $origin,
        RateAddress $destination,
        array $packages,
        ?string $carrier = null,
        array $options = []
    ): array {
        $origin = new AddressData(...get_object_vars($origin));
        $destination = new AddressData(...get_object_vars($destination));
        $packages = array_map(static fn (RatePackage $package): PackageData => new PackageData(...get_object_vars($package)), $packages);

        if ($carrier !== null) {
            $driver = $this->shippingManager->driver($carrier);

            return $driver->getRates($origin, $destination, $packages, $options)->map($this->toQuote(...))->values()->all();
        }

        // Get rates from all registered drivers (skip drivers that don't service destination)
        $rates = collect();

        foreach ($this->shippingManager->getAvailableDrivers() as $carrierName) {
            try {
                $driver = $this->shippingManager->driver($carrierName);

                if (! $driver->servicesDestination($destination)) {
                    continue;
                }

                $carrierRates = $driver->getRates($origin, $destination, $packages, $options);
                $rates = $rates->merge($carrierRates);
            } catch (Throwable $e) {
                report($e);

                continue;
            }
        }

        return $rates->sortBy('rate')->map($this->toQuote(...))->values()->all();
    }

    private function toQuote(RateQuoteData $rate): RateQuote
    {
        return new RateQuote(
            carrier: $rate->carrier,
            service: $rate->service,
            rate: $rate->rate,
            currency: $rate->currency,
            estimatedDays: $rate->estimatedDays,
            estimatedDeliveryDate: $rate->estimatedDeliveryDate,
            serviceDescription: $rate->serviceDescription,
            restrictions: $rate->restrictions,
            calculatedLocally: $rate->calculatedLocally,
            quoteId: $rate->quoteId,
            expiresAt: $rate->expiresAt === null ? null : \DateTimeImmutable::createFromInterface($rate->expiresAt),
            note: $rate->note,
        );
    }
}
