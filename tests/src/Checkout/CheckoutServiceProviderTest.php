<?php

declare(strict_types=1);

use AIArmada\Checkout\CheckoutServiceProvider;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Support\ChipIntegrationRegistrar;
use AIArmada\Checkout\Support\HandleChipPurchaseEventForCheckout;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use Illuminate\Support\Facades\Event;

describe('CheckoutServiceProvider', function (): void {
    it('provides correct services list', function (): void {
        $provider = new CheckoutServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain('checkout')
            ->and($provides)->toContain(CheckoutService::class)
            ->and($provides)->toContain(CheckoutServiceInterface::class)
            ->and($provides)->toContain(CheckoutStepRegistry::class)
            ->and($provides)->toContain(CheckoutStepRegistryInterface::class)
            ->and($provides)->toContain(PaymentGatewayResolver::class)
            ->and($provides)->toContain(PaymentGatewayResolverInterface::class);
    });

    it('provides correct services count', function (): void {
        $provider = new CheckoutServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toHaveCount(7);
    });

    it('does not register chip listeners when the chip checkout integration is disabled', function (): void {
        config()->set('checkout.integrations.chip.enabled', false);

        Event::shouldReceive('listen')->never();

        $registrar = new ChipIntegrationRegistrar;

        $registrar->register();
    });

    it('registers chip listeners when the chip checkout integration is enabled', function (): void {
        config()->set('checkout.integrations.chip.enabled', true);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchasePaid::class, HandleChipPurchaseEventForCheckout::class);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchasePaymentFailure::class, HandleChipPurchaseEventForCheckout::class);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchaseCancelled::class, HandleChipPurchaseEventForCheckout::class);

        $registrar = new ChipIntegrationRegistrar;

        $registrar->register();
    });
});
