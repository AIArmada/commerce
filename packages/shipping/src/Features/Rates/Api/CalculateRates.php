<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates\Api;

interface CalculateRates
{
    /**
     * @param array<RatePackage> $packages
     * @param array<string, mixed> $options
     * @return list<RateQuote>
     */
    public function handle(RateAddress $origin, RateAddress $destination, array $packages, ?string $carrier = null, array $options = []): array;
}
