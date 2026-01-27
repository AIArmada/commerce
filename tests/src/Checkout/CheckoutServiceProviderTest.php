<?php

declare(strict_types=1);

use AIArmada\Checkout\CheckoutServiceProvider;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;

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
});
