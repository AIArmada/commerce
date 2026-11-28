<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Listeners\RedeemVouchersOnCheckoutCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherStatus;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery\Expectation;

use function Pest\Laravel\mock;

it('normalizes voucher validation results', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-1';
    $session->subtotal = 1200;
    $session->currency = 'MYR';

    $adapter = new VouchersAdapter;

    if (! interface_exists(VoucherServiceInterface::class)) {
        $result = $adapter->validateVoucher('CODE', $session);

        expect($result['valid'])->toBeFalse()
            ->and($result['message'])->toBe('Vouchers not available')
            ->and($result['voucher'])->toBeNull();

        return;
    }

    $status = VoucherStatus::Active;

    $voucherData = new VoucherData(
        id: 'voucher-1',
        code: 'CODE',
        name: 'Test Voucher',
        description: null,
        type: VoucherType::Fixed,
        value: 500,
        valueConfig: null,
        creditDestination: null,
        creditDelayHours: 0,
        currency: 'MYR',
        minCartValue: null,
        maxDiscount: null,
        usageLimit: null,
        usageLimitPerUser: null,
        allowsManualRedemption: false,
        ownerId: null,
        ownerType: null,
        startsAt: null,
        expiresAt: null,
        status: $status,
        targetDefinition: null,
        metadata: null,
    );

    $service = mock(VoucherServiceInterface::class);
    /** @var Expectation $validateExpectation */
    $validateExpectation = $service->shouldReceive('validate');
    $validateExpectation->once()
        ->andReturn(VoucherValidationResult::valid(['voucher' => $voucherData->toArray()]));
    $service->shouldNotReceive('find');

    app()->instance(VoucherServiceInterface::class, $service);

    $result = $adapter->validateVoucher('CODE', $session);

    expect($result['valid'])->toBeTrue()
        ->and($result['message'])->toBeNull()
        ->and($result['voucher'])->toBeArray()
        ->and($result['voucher']['code'])->toBe('CODE')
        ->and($result['voucher']['type'])->toBe('fixed');
});

it('applies a promo_code when it resolves to a voucher', function (): void {
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    Cart::add('sku-unified-voucher', 'Unified Voucher Product', 10000, 1, ['sku' => 'UNIFIED-001']);

    Voucher::query()->create([
        'code' => 'PROMO10',
        'name' => 'Promo Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $session = new CheckoutSession;
    $session->id = 'session-unified-voucher';
    $session->cart_id = (string) Cart::getId();
    $session->subtotal = 10000;
    $session->currency = 'USD';
    $session->billing_data = ['metadata' => ['promo_code' => 'PROMO10']];
    $session->cart_snapshot = ['metadata' => []];

    $result = (new VouchersAdapter)->applyVouchers($session, []);

    expect($result['discount'])->toBe(1000)
        ->and($result['applied'])->toHaveCount(1)
        ->and($result['applied'][0])->toMatchArray([
            'code' => 'PROMO10',
            'type' => 'percentage',
            'discount' => 1000,
            'promotion_id' => null,
        ]);
});

it('calculates buy x get y voucher discounts against the live cart', function (): void {
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    Cart::add('SHIRT-001', 'Shirt', 2000, 3, ['sku' => 'SHIRT-001']);

    Voucher::query()->create([
        'code' => 'BOGO2GET1',
        'name' => 'Buy 2 Get 1 Free',
        'type' => VoucherType::BuyXGetY,
        'value' => 0,
        'value_config' => [
            'buy' => [
                'quantity' => 2,
                'product_matcher' => ['type' => 'sku', 'skus' => ['SHIRT-001']],
            ],
            'get' => [
                'quantity' => 1,
                'discount' => '100%',
                'selection' => 'cheapest',
                'product_matcher' => ['type' => 'same_as_buy'],
            ],
        ],
        'currency' => 'USD',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $session = new CheckoutSession;
    $session->id = 'session-bogo-voucher';
    $session->cart_id = (string) Cart::getId();
    $session->subtotal = 6000;
    $session->currency = 'USD';
    $session->billing_data = [];
    $session->cart_snapshot = ['metadata' => []];

    $result = (new VouchersAdapter)->applyVouchers($session, ['BOGO2GET1']);

    expect($result['discount'])->toBe(2000)
        ->and($result['applied'])->toHaveCount(1)
        ->and($result['applied'][0])->toMatchArray([
            'code' => 'BOGO2GET1',
            'type' => 'buy_x_get_y',
            'discount' => 2000,
        ]);
});

it('dispatches voucher applied events for live cart checkout voucher applications', function (): void {
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    Cart::add('sku-event-voucher', 'Event Voucher Product', 10000, 1, ['sku' => 'EVENT-001']);

    Voucher::query()->create([
        'code' => 'EVENT10',
        'name' => 'Event Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $session = new CheckoutSession;
    $session->id = 'session-event-voucher';
    $session->cart_id = 'cart-live-event-voucher';
    $session->subtotal = 10000;
    $session->currency = 'USD';
    $session->billing_data = [];
    $session->cart_snapshot = ['metadata' => []];

    $liveCart = app('cart')->getCurrentCart();
    $cartManager = mock(CartManagerInterface::class);
    $cartManager->shouldReceive('getById')
        ->once()
        ->with('cart-live-event-voucher')
        ->andReturn($liveCart);

    Event::fake([VoucherApplied::class]);

    $result = (new VouchersAdapter)->applyVouchers($session, ['EVENT10']);

    expect($result['discount'])->toBe(1000);

    Event::assertDispatched(
        VoucherApplied::class,
        fn (VoucherApplied $event): bool => $event->cart->getIdentifier() === $liveCart->getIdentifier()
            && $event->voucher->code === 'EVENT10',
    );
});

it('does not dispatch voucher application events during discount evaluation', function (): void {
    Cart::clear();
    Cart::clearConditions();
    Cart::clearMetadata();
    Cart::clearVouchers();

    Cart::add('sku-evaluate-voucher', 'Evaluate Voucher Product', 10000, 1, ['sku' => 'EVALUATE-001']);

    Voucher::query()->create([
        'code' => 'EVALUATE10',
        'name' => 'Evaluate Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $session = new CheckoutSession;
    $session->id = 'session-evaluate-voucher';
    $session->cart_id = 'cart-evaluate-voucher';
    $session->subtotal = 10000;
    $session->currency = 'USD';
    $session->billing_data = [];
    $session->cart_snapshot = ['metadata' => []];

    $liveCart = app('cart')->getCurrentCart();
    $cartManager = mock(CartManagerInterface::class);
    $cartManager->shouldReceive('getById')
        ->once()
        ->with('cart-evaluate-voucher')
        ->andReturn($liveCart);

    Event::fake([VoucherApplied::class]);

    $result = (new VouchersAdapter)->evaluate($session, ['voucher_codes' => ['EVALUATE10']]);

    expect($result)->toHaveCount(1);
    Event::assertNotDispatched(VoucherApplied::class);
});

it('reserves and redeems the committed voucher identity when a tenant shadows a global code', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Checkout Voucher Identity Owner',
        'email' => 'checkout-voucher-identity@example.com',
        'password' => 'secret',
    ]);

    $attributes = [
        'code' => 'CHECKOUT-IDENTITY',
        'name' => 'Checkout Identity',
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'MYR',
        'status' => 'active',
    ];

    $global = OwnerContext::withOwner(null, static fn (): Voucher => Voucher::query()->create($attributes));
    $owned = OwnerContext::withOwner($owner, static fn (): Voucher => Voucher::query()->create($attributes));

    OwnerContext::withOwner($owner, function () use ($global, $owned): void {
        $order = Order::factory()->create(['currency' => 'MYR']);
        $session = new CheckoutSession;
        $session->id = 'voucher-identity-session';
        $session->order_id = $order->getKey();
        $session->currency = 'MYR';
        $session->subtotal = 1000;

        $adapter = new VouchersAdapter;
        $commitments = $adapter->commit($session, [
            new DiscountProposal('vouchers', 'voucher:CHECKOUT-IDENTITY', 100, code: 'CHECKOUT-IDENTITY', meta: ['voucher_id' => $global->id]),
        ]);

        expect(Cache::get("voucher_reservation:{$global->id}:voucher-identity-session"))->toBeArray()
            ->and(Cache::get("voucher_reservation:{$owned->id}:voucher-identity-session"))->toBeNull();

        $session->discount_data = ['commitments' => array_map(static fn ($commitment): array => $commitment->toArray(), $commitments)];
        app(RedeemVouchersOnCheckoutCompleted::class)->handle(new CheckoutCompleted($session));

        expect($global->usages()->count())->toBe(1)
            ->and($owned->usages()->count())->toBe(0)
            ->and(Cache::get("voucher_reservation:{$global->id}:voucher-identity-session"))->toBeNull();
    });
});

it('rejects a checkout voucher commitment without an exact identity', function (): void {
    $session = new CheckoutSession;
    $session->id = 'missing-voucher-id';

    expect(fn () => (new VouchersAdapter)->commit($session, [
        new DiscountProposal('vouchers', 'voucher:CODE', 100, code: 'CODE'),
    ]))->toThrow(InvalidArgumentException::class);
});
