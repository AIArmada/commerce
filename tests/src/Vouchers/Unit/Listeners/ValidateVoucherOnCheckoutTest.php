<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Listeners\ValidateVoucherOnCheckout;
use AIArmada\Vouchers\Services\VoucherService;
use Mockery;

/**
 * Create a cart for testing.
 *
 * @param  array<string, mixed>  $metadata
 */
function createCartForListenerTest(array $metadata = []): Cart
{
    $cart = new Cart(new InMemoryStorage(), 'listener-test-' . uniqid());

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

beforeEach(function (): void {
    $this->voucherService = Mockery::mock(VoucherService::class);
    $this->listener = new ValidateVoucherOnCheckout($this->voucherService);
});

afterEach(function (): void {
    Mockery::close();
});

describe('ValidateVoucherOnCheckout Handle', function (): void {
    it('does nothing when event has no cart', function (): void {
        $event = createEventWithoutCart();

        // Should not throw and not call service
        $this->voucherService->shouldNotReceive('validate');

        $this->listener->handle($event);

        expect(true)->toBeTrue(); // Listener completes without error
    });

    it('does nothing when cart property is not Cart instance', function (): void {
        $event = createEventWithInvalidCart();

        $this->voucherService->shouldNotReceive('validate');

        $this->listener->handle($event);

        expect(true)->toBeTrue();
    });

    it('does nothing when no voucher codes in cart', function (): void {
        $cart = createCartForListenerTest();
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldNotReceive('validate');

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBeNull();
    });

    it('does nothing when voucher codes array is empty', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => []]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldNotReceive('validate');

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe([]);
    });

    it('keeps valid voucher codes', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID1', 'VALID2']]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldReceive('validate')
            ->with('VALID1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
            ->with('VALID2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID1', 'VALID2']);
    });

    it('removes invalid voucher codes', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID', 'EXPIRED', 'INVALID']]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));

        $this->voucherService->shouldReceive('validate')
            ->with('INVALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Usage limit reached'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['VALID']);
    });

    it('removes all codes when all invalid', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['EXPIRED1', 'EXPIRED2']]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Expired'));

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Expired'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe([]);
    });

    it('uses default message when validation result has no message', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['NOMSGINVALID']]);
        $event = createCheckoutEvent($cart);

        // Create result without message
        $result = new VoucherValidationResult(isValid: false, reason: null);

        $this->voucherService->shouldReceive('validate')
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
        // Set config to block on invalid vouchers
        config(['vouchers.checkout.block_on_invalid' => true]);
    });

    afterEach(function (): void {
        config(['vouchers.checkout.block_on_invalid' => false]);
    });

    it('throws exception when configured to block and voucher invalid', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['EXPIRED']]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));

        expect(fn () => $this->listener->handle($event))
            ->toThrow(VoucherValidationException::class);
    });

    it('does not throw when all vouchers valid in block mode', function (): void {
        $cart = createCartForListenerTest(['voucher_codes' => ['VALID']]);
        $event = createCheckoutEvent($cart);

        $this->voucherService->shouldReceive('validate')
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

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED1', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('First expired'));

        $this->voucherService->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
            ->with('EXPIRED2', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Second expired'));

        try {
            $this->listener->handle($event);
            $this->fail('Expected VoucherValidationException');
        } catch (VoucherValidationException $e) {
            // Exception should be thrown
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

        $this->voucherService->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
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

        $this->voucherService->shouldReceive('validate')
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

        foreach ($codes as $code) {
            $this->voucherService->shouldReceive('validate')
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

        $this->voucherService->shouldReceive('validate')
            ->with('A', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
            ->with('B', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Invalid'));

        $this->voucherService->shouldReceive('validate')
            ->with('C', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::valid());

        $this->voucherService->shouldReceive('validate')
            ->with('D', $cart)
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Invalid'));

        $this->listener->handle($event);

        expect($cart->getMetadata('voucher_codes'))->toBe(['A', 'C']);
    });
});
