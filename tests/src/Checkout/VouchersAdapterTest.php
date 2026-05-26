<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\VoucherStatus;
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

    $status = VoucherStatus::fromString('active', new Voucher);

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
        ->andReturn(VoucherValidationResult::valid());
    /** @var Expectation $findExpectation */
    $findExpectation = $service->shouldReceive('find');
    $findExpectation->once()
        ->andReturn($voucherData);

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
