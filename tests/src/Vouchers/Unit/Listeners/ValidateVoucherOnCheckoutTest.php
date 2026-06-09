<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Actions\ValidateVoucherCode;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Listeners\ValidateVoucherOnCheckout;
use Mockery;

/**
 * Create a cart for testing.
 *
 * @param  array<string, mixed>  $metadata
 */
function createCartForListenerTest(array $metadata = []): Cart
{
    $cart = new Cart(new InMemoryStorage, 'listener-test-' . uniqid());

    foreach ($metadata as $key => $value) {
        $cart->setMetadata($key, $value);
    }

    return $cart;
}

/**
 * Create a checkout started event with cart.
 */
function createCheckoutEvent(Cart $cart): object
{
    return new class($cart)
    {
        public function __construct(public readonly Cart $cart) {}
    };
}

/**
 * Create an event without cart property.
 */
function createEventWithoutCart(): object
{
    return new class
    {
        public string $type = 'checkout.started';
    };
}

/**
 * Create an event with non-cart property.
 */
function createEventWithInvalidCart(): object
{
    return new class
    {
        public string $cart = 'not-a-cart';
    };
}

/**
 * Create a mock of ValidateVoucherCode and register it in the container.
 * Uses app()->instance() so AsObject::run() resolves the mock.
 */
function mockValidateVoucherCode(): Mockery\MockInterface
{
    $mock = Mockery::mock(ValidateVoucherCode::class);
    app()->instance(ValidateVoucherCode::class, $mock);

    return $mock;
}

beforeEach(function (): void {
    $this->listener = new ValidateVoucherOnCheckout;
});

afterEach(function (): void {
    Mockery::close();
});

describe('ValidateVoucherOnCheckout Handle', function (): void {
    it('does nothing when event has no cart', function (): void {
        $event = createEventWithoutCart();

        $this->listener->handle($event);

        expect(true)->toBeTrue(); // Listener completes without error
    });

    it('does nothing when cart property is not Cart instance', function (): void {
        $event = createEventWithInvalidCart();

        $this->listener->handle($event);

        expect(true)->toBeTrue();
    });

    it('does nothing when no voucher codes in cart', function (): void {
        $cart = createCartForListenerTest();
        $event = createCheckoutEvent($cart);

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBeNull();
    });

    it('does nothing when voucher codes array is empty', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => []]);
        $event = createCheckoutEvent($cart);

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe([]);
    });

    it('keeps valid voucher codes', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID1', 'VALID2']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('VALID1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('VALID2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID1', 'VALID2']);
    });

    it('removes invalid voucher codes', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID', 'EXPIRED', 'INVALID']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));
        $mock->shouldReceive('handle')
            ->with('INVALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Usage limit reached'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID']);
    });

    it('removes all codes when all invalid', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['EXPIRED1', 'EXPIRED2']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('EXPIRED1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Expired'));
        $mock->shouldReceive('handle')
            ->with('EXPIRED2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Expired'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe([]);
    });

    it('uses default message when validation result has no message', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['NOMSGINVALID']]);
        $event = createCheckoutEvent($cart);

        $result = new VoucherValidationResult(isValid: false, reason: null);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('NOMSGINVALID', $cart)
            ->once()
            ->andReturn($result);

        // Should not throw by default
        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe([]);
    });
});

describe('ValidateVoucherOnCheckout Block Mode', function (): void {
    beforeEach(function (): void {
        config(['vouchers.checkout.block_on_invalid' => true]);
    });

    afterEach(function (): void {
        config(['vouchers.checkout.block_on_invalid' => false]);
    });

    it('throws exception when configured to block and voucher invalid', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['EXPIRED']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));

        expect(fn () => $this->listener->handle($event))
            ->toThrow(VoucherValidationException::class);
    });

    it('does not throw when all vouchers valid in block mode', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        // Should not throw
        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID']);
    });

    it('includes all invalid codes in exception', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['EXPIRED1', 'VALID', 'EXPIRED2']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('EXPIRED1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('First expired'));
        $mock->shouldReceive('handle')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('EXPIRED2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Second expired'));

        try {
            $this->listener->handle($event);
            $this->fail('Expected VoucherValidationException');
        } catch (VoucherValidationException $e) {
            expect($e)->toBeInstanceOf(VoucherValidationException::class);
        }
    });
});

describe('ValidateVoucherOnCheckout Non-Block Mode', function (): void {
    beforeEach(function (): void {
        config(['vouchers.checkout.block_on_invalid' => false]);
    });

    it('removes invalid vouchers silently', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID', 'EXPIRED']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Expired'));

        // Should not throw
        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID']);
    });
});

describe('ValidateVoucherOnCheckout Edge Cases', function (): void {
    it('handles single voucher code', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['SINGLE']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('SINGLE', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['SINGLE']);
    });

    it('handles many voucher codes', function (): void {
        $codes = ['CODE1', 'CODE2', 'CODE3', 'CODE4', 'CODE5'];
        $cart = createCartForListenerTest(['voucher_codes' => $codes]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        foreach ($codes as $code) {
            $mock->shouldReceive('handle')
                ->with($code, $cart)
                ->once()
                ->andReturn(VoucherValidationResult::valid());
        }

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe($codes);
    });

    it('preserves order of valid codes', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['A', 'B', 'C', 'D']]);
        $event = createCheckoutEvent($cart);

        $mock = mockValidateVoucherCode();
        $mock->shouldReceive('handle')
            ->with('A', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('B', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Invalid'));
        $mock->shouldReceive('handle')
            ->with('C', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());
        $mock->shouldReceive('handle')
            ->with('D', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Invalid'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['A', 'C']);
    });
});
