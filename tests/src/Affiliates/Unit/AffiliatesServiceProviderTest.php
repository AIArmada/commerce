<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\AffiliateNetworkServiceProvider;
use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Listeners\AutoApproveOpenAffiliate;
use AIArmada\Affiliates\Listeners\IncrementAffiliateLinkClicks;
use AIArmada\Affiliates\Listeners\NotifyConversionRecorded;
use AIArmada\Affiliates\Listeners\NotifyProgramJoined;
use AIArmada\Affiliates\Listeners\RecordCommissionForOrder;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Middleware\HydratePublicAffiliateReferralContext;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Event;

afterEach(function (): void {
    Mockery::close();
});

it('registers voucher and cart-condition integrations when their features are enabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', true);
    config()->set('affiliates.features.voucher_integration.enabled', true);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
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

    expect($voucherRegistrar->calls)->toBe(1)
        ->and($registry->calls)->toBe(1);
});

it('skips cart and voucher integrations when their features are disabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
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

    expect($voucherRegistrar->calls)->toBe(0)
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
    Event::shouldReceive('listen')->once()->with(LinkClicked::class, IncrementAffiliateLinkClicks::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateProgramJoined::class, NotifyProgramJoined::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateConversionRecorded::class, NotifyConversionRecorded::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateConversionRecorded::class, AutoApproveOpenAffiliate::class);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();
});

it('does not register the commission listener when commission tracking is disabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);

    Event::shouldReceive('listen')->once()->with(LinkClicked::class, IncrementAffiliateLinkClicks::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateProgramJoined::class, NotifyProgramJoined::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateConversionRecorded::class, NotifyConversionRecorded::class);
    Event::shouldReceive('listen')
        ->once()
        ->with(AffiliateConversionRecorded::class, AutoApproveOpenAffiliate::class);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();
});

it('registers one engine conversion listener across affiliate-network checkout gates', function (): void {
    config()->set('affiliate-network.checkout.enabled', false);
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
    config()->set('affiliates.public_pages.enabled', false);

    app()->register(AffiliateNetworkServiceProvider::class);

    $cases = [
        ['checkout' => false, 'listen' => true, 'tracking' => true, 'expected' => 1],
        ['checkout' => true, 'listen' => true, 'tracking' => true, 'expected' => 1],
        ['checkout' => true, 'listen' => false, 'tracking' => true, 'expected' => 1],
        ['checkout' => true, 'listen' => true, 'tracking' => false, 'expected' => 0],
    ];

    foreach ($cases as $case) {
        Event::forget(CommissionAttributionRequired::class);
        config()->set('affiliate-network.checkout.enabled', $case['checkout']);
        config()->set('affiliate-network.checkout.listen_for_orders', $case['listen']);
        config()->set('affiliates.features.commission_tracking.enabled', $case['tracking']);

        (new AffiliatesServiceProvider(app()))->packageBooted();
        (new AffiliateNetworkServiceProvider(app()))->packageBooted();

        $listeners = Event::getRawListeners()[CommissionAttributionRequired::class] ?? [];
        $engineListeners = array_values(array_filter(
            $listeners,
            static fn (mixed $listener): bool => $listener === RecordCommissionForOrder::class,
        ));

        expect($engineListeners)->toHaveCount($case['expected']);

        if (! $case['tracking']) {
            continue;
        }

        $affiliate = createTestAffiliate();
        $cart = new Cart(new InMemoryStorage, 'w1-cart-' . uniqid(), events: null, eventsEnabled: false);
        $cart->add('w1-item-' . uniqid(), 'Commission item', 10000, 1);
        app(AttachAffiliateToCart::class)->handle($affiliate, $cart);

        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getById')->once()->with($cart->getId())->andReturn($cart);
        app()->instance(CartManagerInterface::class, $cartManager);

        $order = Order::factory()->paid()->create([
            'metadata' => ['cart_id' => $cart->getId()],
        ]);

        event(new CommissionAttributionRequired($order));

        expect(AffiliateConversion::query()->where('affiliate_id', $affiliate->getKey())->count())
            ->toBe($case['expected']);
    }
});

it('registers public-page referral middleware when the feature is enabled', function (): void {
    config()->set('affiliates.features.cart_integration.enabled', false);
    config()->set('affiliates.features.voucher_integration.enabled', false);
    config()->set('affiliates.features.commission_tracking.enabled', false);
    config()->set('affiliates.cookies.enabled', false);
    config()->set('affiliates.public_pages.enabled', true);
    config()->set('affiliates.public_pages.auto_register_middleware', true);

    $provider = new AffiliatesServiceProvider(app());
    $provider->packageBooted();

    $router = app('router');

    expect($router->getMiddleware()['affiliates.public_context'] ?? null)
        ->toBe(HydratePublicAffiliateReferralContext::class)
        ->and($router->getMiddlewareGroups()['web'] ?? [])->toContain(HydratePublicAffiliateReferralContext::class);
});
