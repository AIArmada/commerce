<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Services\CartValidator;
use AIArmada\Cart\Services\ValidationError;
use AIArmada\Cart\Services\ValidationResult;
use AIArmada\Cart\Storage\CacheStorage;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $storage = new CacheStorage(Cache::store(), 'validator_test', 3600);
    $this->cart = new Cart($storage, 'test-user', null, 'default');
});

describe('CartValidator', function (): void {
    describe('basic validation', function (): void {
        it('passes validation on valid cart', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1); // 5000 cents = $50

            $validator = CartValidator::create()
                ->requireNonEmpty();

            $result = $validator->validate($this->cart);

            expect($result)->toBeInstanceOf(ValidationResult::class);
            expect($result->hasPassed())->toBeTrue();
            expect($result->getErrors())->toBeEmpty();
        });

        it('returns ValidationResult instance', function (): void {
            $validator = CartValidator::create();

            $result = $validator->validate($this->cart);

            expect($result)->toBeInstanceOf(ValidationResult::class);
        });
    });

    describe('require non empty', function (): void {
        it('fails on empty cart', function (): void {
            $validator = CartValidator::create()
                ->requireNonEmpty();

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getErrors())->toHaveCount(1);
            expect($result->getFirstError()->rule)->toBe('non_empty');
        });

        it('passes with items', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::create()
                ->requireNonEmpty();

            $result = $validator->validate($this->cart);

            expect($result->hasPassed())->toBeTrue();
        });
    });

    describe('minimum total', function (): void {
        it('fails when below minimum', function (): void {
            $this->cart->add('product-1', 'Cheap Product', 3000, 1); // 3000 cents = $30

            $validator = CartValidator::create()
                ->requireMinimumTotal(5000); // 5000 cents = $50

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getFirstError()->rule)->toBe('minimum_total');
        });

        it('passes when above minimum', function (): void {
            $this->cart->add('product-1', 'Nice Product', 10000, 1); // 10000 cents = $100

            $validator = CartValidator::create()
                ->requireMinimumTotal(5000);

            $result = $validator->validate($this->cart);

            expect($result->hasPassed())->toBeTrue();
        });

        it('uses custom message', function (): void {
            $this->cart->add('product-1', 'Cheap Product', 3000, 1);

            $validator = CartValidator::create()
                ->requireMinimumTotal(5000, 'Order must be at least $50');

            $result = $validator->validate($this->cart);

            expect($result->getFirstError()->message)->toBe('Order must be at least $50');
        });
    });

    describe('maximum total', function (): void {
        it('fails when above maximum', function (): void {
            $this->cart->add('product-1', 'Expensive Product', 100000, 1); // 100000 cents = $1000

            $validator = CartValidator::create()
                ->requireMaximumTotal(50000); // 50000 cents = $500

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getFirstError()->rule)->toBe('maximum_total');
        });

        it('passes when below maximum', function (): void {
            $this->cart->add('product-1', 'Product', 3000, 1); // 3000 cents = $30

            $validator = CartValidator::create()
                ->requireMaximumTotal(50000);

            $result = $validator->validate($this->cart);

            expect($result->hasPassed())->toBeTrue();
        });
    });

    describe('maximum items', function (): void {
        it('fails when items exceed maximum', function (): void {
            $this->cart->add('product-1', 'Product', 1000, 15); // 15 items

            $validator = CartValidator::create()
                ->requireMaximumItems(10);

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getFirstError()->rule)->toBe('maximum_items');
        });

        it('passes when items within limit', function (): void {
            $this->cart->add('product-1', 'Product', 1000, 5); // 5 items

            $validator = CartValidator::create()
                ->requireMaximumItems(10);

            $result = $validator->validate($this->cart);

            expect($result->hasPassed())->toBeTrue();
        });
    });

    describe('custom rules', function (): void {
        it('supports custom item rules', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::create()
                ->addRule('custom', fn ($item) => $item->price > 10000 ? null : 'Price too low');

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getFirstError()->message)->toBe('Price too low');
        });

        it('passes when rule returns null', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::create()
                ->addRule('custom', fn ($item) => null); // Always passes

            $result = $validator->validate($this->cart);

            expect($result->hasPassed())->toBeTrue();
        });

        it('fails when rule returns false', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::create()
                ->addRule('custom', fn ($item) => false); // Always fails

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
        });

        it('supports custom cart rules', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::create()
                ->addCartRule('custom', fn ($cart) => 'Custom cart error');

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
            expect($result->getFirstError()->message)->toBe('Custom cart error');
        });
    });

    describe('stop on first error', function (): void {
        it('stops on first error when enabled', function (): void {
            $validator = CartValidator::create()
                ->stopOnFirstError()
                ->requireNonEmpty()
                ->requireMinimumTotal(5000);

            $result = $validator->validate($this->cart);

            expect($result->getErrors())->toHaveCount(1);
        });

        it('collects all errors when disabled', function (): void {
            $validator = CartValidator::create()
                ->stopOnFirstError(false)
                ->requireNonEmpty()
                ->addCartRule('always_fail', fn ($cart) => 'Always fails');

            $result = $validator->validate($this->cart);

            expect($result->getErrors())->toHaveCount(2);
        });
    });

    describe('checkout factory', function (): void {
        it('creates validator with checkout rules', function (): void {
            $this->cart->add('product-1', 'Test Product', 5000, 1);

            $validator = CartValidator::forCheckout();

            $result = $validator->validate($this->cart);

            // Should pass for simple item (no buyable interface checks apply)
            expect($result->hasPassed())->toBeTrue();
        });

        it('fails on empty cart', function (): void {
            $validator = CartValidator::forCheckout();

            $result = $validator->validate($this->cart);

            expect($result->hasFailed())->toBeTrue();
        });
    });

    describe('validation result', function (): void {
        it('gets messages as array', function (): void {
            $validator = CartValidator::create()
                ->requireNonEmpty()
                ->addCartRule('fail', fn ($cart) => 'Another error');

            $result = $validator->validate($this->cart);

            $messages = $result->getMessages();
            expect($messages)->toBeArray();
            expect($messages)->toHaveCount(2);
        });

        it('separates cart and item errors', function (): void {
            $this->cart->add('product-1', 'Product', 1000, 1);

            $validator = CartValidator::create()
                ->addCartRule('cart_fail', fn ($cart) => 'Cart error')
                ->addRule('item_fail', fn ($item) => 'Item error');

            $result = $validator->validate($this->cart);

            expect($result->getCartErrors())->toHaveCount(1);
            expect($result->getItemErrors())->toHaveCount(1);
        });

        it('gets errors for specific item', function (): void {
            $this->cart->add('product-1', 'Product 1', 1000, 1);
            $this->cart->add('product-2', 'Product 2', 2000, 1);

            $validator = CartValidator::create()
                ->addRule('check', fn ($item) => $item->id === 'product-1' ? 'Error for product-1' : null);

            $result = $validator->validate($this->cart);

            expect($result->getErrorsForItem('product-1'))->toHaveCount(1);
            expect($result->getErrorsForItem('product-2'))->toHaveCount(0);
        });
    });

    describe('validation error', function (): void {
        it('identifies item vs cart errors', function (): void {
            $itemError = ValidationError::item('item-1', 'rule', 'message');
            $cartError = ValidationError::cart('rule', 'message');

            expect($itemError->isItemError())->toBeTrue();
            expect($itemError->isCartError())->toBeFalse();
            expect($cartError->isCartError())->toBeTrue();
            expect($cartError->isItemError())->toBeFalse();
        });

        it('gets error message', function (): void {
            $error = ValidationError::cart('rule', 'Test message');

            expect($error->getMessage())->toBe('Test message');
        });
    });
});
