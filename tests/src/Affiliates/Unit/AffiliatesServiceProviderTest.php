<?php

declare(strict_types=1);

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Affiliates\Listeners\RecordCommissionForOrder;
use AIArmada\Affiliates\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Illuminate\Support\Facades\Event;

afterEach(function (): void {
    Mockery::close();
});

it('registers cart and voucher integrations when their features are enabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', true);
    config()->set('affiliates.features.voucher_integration.enabled', true);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
    config()->set('affiliates.cart.register_manager_proxy', true);

    $cartRegistrar = new class
    {
        public int $calls = 0;

        public function register(): void
        {
            $this->calls++;
        }
    };
    app()->instance(CartIntegrationRegistrar::class, $cartRegistrar);

    $voucherRegistrar = new class
    {
        public int $calls = 0;

        public function register(): void
        {
            $this->calls++;
        }
    };
    app()->instance(VoucherIntegrationRegistrar::class, $voucherRegistrar);

    $registry = new class
    {
        public int $calls = 0;

        public function register(string $provider): void
        {
            $this->calls++;
        }
    };
    app()->instance(ConditionProviderRegistry::class, $registry);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();

    expect($cartRegistrar->calls)->toBe(1)
        ->and($voucherRegistrar->calls)->toBe(1)
        ->and($registry->calls)->toBe(1);
});

it('skips cart and voucher integrations when their features are disabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
    config()->set('affiliates.cart.register_manager_proxy', true);

    $cartRegistrar = new class
    {
        public int $calls = 0;

        public function register(): void
        {
            $this->calls++;
        }
    };
    app()->instance(CartIntegrationRegistrar::class, $cartRegistrar);

    $voucherRegistrar = new class
    {
        public int $calls = 0;

        public function register(): void
        {
            $this->calls++;
        }
    };
    app()->instance(VoucherIntegrationRegistrar::class, $voucherRegistrar);

    $registry = new class
    {
        public int $calls = 0;

        public function register(string $provider): void
        {
            $this->calls++;
        }
    };
    app()->instance(ConditionProviderRegistry::class, $registry);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();

    expect($cartRegistrar->calls)->toBe(0)
        ->and($voucherRegistrar->calls)->toBe(0)
        ->and($registry->calls)->toBe(0);
});

it('registers the commission listener only when commission tracking is enabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', true);
    config()->set('affiliates.cookies.enabled', false);

    Event::shouldReceive('listen')
        ->once()
        ->with(CommissionAttributionRequired::class, RecordCommissionForOrder::class);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();
});

it('does not register the commission listener when commission tracking is disabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);

    Event::shouldReceive('listen')->never();

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();
});
