<?php

declare(strict_types=1);

use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\PricingServiceProvider;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;

describe('PricingServiceProvider', function (): void {
    beforeEach(function (): void {
        // Manually register the service provider for testing
        $provider = new PricingServiceProvider(app());
        $provider->register();
        $provider->boot();
    });

    describe('service provider methods', function (): void {
        it('can be instantiated', function (): void {
            $provider = new PricingServiceProvider(app());

            expect($provider)->toBeInstanceOf(PricingServiceProvider::class);
        });

        it('has register method', function (): void {
            $provider = new PricingServiceProvider(app());

            expect(method_exists($provider, 'register'))->toBeTrue();
        });

        it('has boot method', function (): void {
            $provider = new PricingServiceProvider(app());

            expect(method_exists($provider, 'boot'))->toBeTrue();
        });
    });

    describe('PriceCalculator', function (): void {
        it('can be instantiated', function (): void {
            $calculator = new PriceCalculator(
                new TierResolver,
                new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
                new CustomerPriceResolver,
                new SegmentPriceResolver,
            );

            expect($calculator)->toBeInstanceOf(PriceCalculator::class);
        });

        it('has calculate method', function (): void {
            $calculator = new PriceCalculator(
                new TierResolver,
                new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
                new CustomerPriceResolver,
                new SegmentPriceResolver,
            );

            expect(method_exists($calculator, 'calculate'))->toBeTrue();
        });
    });
});
