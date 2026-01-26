<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\States\Active;

describe('VoucherApplied event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart(
            storage: $storage,
            identifier: 'event-test-cart',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
            conditionResolver: new CartConditionResolver
        );

        $this->voucherData = VoucherData::fromArray([
            'id' => 'voucher-123',
            'code' => 'TESTCODE',
            'name' => 'Test Voucher',
            'type' => VoucherType::Percentage->value,
            'value' => 1000,
            'currency' => 'MYR',
            'status' => Active::class,
        ]);
    });

    it('can be constructed with cart and voucher data', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event->cart)->toBe($this->cart)
            ->and($event->voucher)->toBe($this->voucherData);
    });

    it('returns correct event type', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event->getEventType())->toBe('voucher.applied');
    });

    it('implements VoucherEventInterface', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event)->toBeInstanceOf(AIArmada\CommerceSupport\Contracts\Events\VoucherEventInterface::class);
    });

    it('uses HasVoucherEventData trait', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        // Trait should initialize event data
        expect(method_exists($event, 'getVoucherCode'))->toBeTrue()
            ->and(method_exists($event, 'getVoucherId'))->toBeTrue()
            ->and(method_exists($event, 'getCartIdentifier'))->toBeTrue();
    });

    it('provides voucher code from trait', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event->getVoucherCode())->toBe('TESTCODE');
    });

    it('provides voucher id from trait', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event->getVoucherId())->toBe('voucher-123');
    });

    it('provides cart identifier from trait', function (): void {
        $event = new VoucherApplied($this->cart, $this->voucherData);

        expect($event->getCartIdentifier())->toBe($this->cart->getIdentifier())
            ->and($event->getCartIdentifier())->toBe('event-test-cart');
    });
});

describe('VoucherRemoved event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart(
            storage: $storage,
            identifier: 'removed-test-cart',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
            conditionResolver: new CartConditionResolver
        );

        $this->voucherData = VoucherData::fromArray([
            'id' => 'voucher-456',
            'code' => 'REMOVEME',
            'name' => 'Removed Voucher',
            'type' => VoucherType::Fixed->value,
            'value' => 500,
            'currency' => 'MYR',
            'status' => Active::class,
        ]);
    });

    it('can be constructed with cart and voucher data', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect($event->cart)->toBe($this->cart)
            ->and($event->voucher)->toBe($this->voucherData);
    });

    it('returns correct event type', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect($event->getEventType())->toBe('voucher.removed');
    });

    it('implements VoucherEventInterface', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect($event)->toBeInstanceOf(AIArmada\CommerceSupport\Contracts\Events\VoucherEventInterface::class);
    });

    it('uses HasVoucherEventData trait', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect(method_exists($event, 'getVoucherCode'))->toBeTrue()
            ->and(method_exists($event, 'getVoucherId'))->toBeTrue();
    });

    it('provides voucher code from trait', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect($event->getVoucherCode())->toBe('REMOVEME');
    });

    it('provides voucher id from trait', function (): void {
        $event = new VoucherRemoved($this->cart, $this->voucherData);

        expect($event->getVoucherId())->toBe('voucher-456');
    });
});

describe('VoucherApplied and VoucherRemoved difference', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart(
            storage: $storage,
            identifier: 'diff-test-cart',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
            conditionResolver: new CartConditionResolver
        );

        $this->voucherData = VoucherData::fromArray([
            'id' => 'voucher-789',
            'code' => 'DIFFTEST',
            'name' => 'Diff Test',
            'type' => VoucherType::Percentage->value,
            'value' => 1500,
            'currency' => 'MYR',
            'status' => Active::class,
        ]);
    });

    it('events have different event types', function (): void {
        $applied = new VoucherApplied($this->cart, $this->voucherData);
        $removed = new VoucherRemoved($this->cart, $this->voucherData);

        expect($applied->getEventType())->not->toBe($removed->getEventType())
            ->and($applied->getEventType())->toBe('voucher.applied')
            ->and($removed->getEventType())->toBe('voucher.removed');
    });

    it('events can share the same voucher data', function (): void {
        $applied = new VoucherApplied($this->cart, $this->voucherData);
        $removed = new VoucherRemoved($this->cart, $this->voucherData);

        expect($applied->voucher)->toBe($this->voucherData)
            ->and($removed->voucher)->toBe($this->voucherData)
            ->and($applied->getVoucherCode())->toBe($removed->getVoucherCode());
    });
});
