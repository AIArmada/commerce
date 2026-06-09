<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Pricing\Contracts\PriceCalculatorInterface;

/**
 * Coordinates how downstream packages (cart, checkout, vouchers, promotions)
 * wire into the pricing system.
 *
 * Packages register their pricing needs through this registrar rather than
 * directly binding to the container or editing the service provider.
 */
final class PricingIntegrationRegistrar
{
    public function __construct(
        private readonly PriceCalculatorInterface $calculator,
    ) {}

    public function calculator(): PriceCalculatorInterface
    {
        return $this->calculator;
    }

    /**
     * Boot registered integrations.
     */
    public function boot(): void
    {
        // Future: wire cart conditions, voucher resolvers, etc.
    }
}
