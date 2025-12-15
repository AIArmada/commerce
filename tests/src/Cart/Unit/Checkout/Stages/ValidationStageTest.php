<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\StageResult;
use AIArmada\Cart\Checkout\Stages\ValidationStage;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\CartValidatorInterface;
use AIArmada\Cart\Testing\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage();
});

describe('ValidationStage', function (): void {
    it('can be instantiated without validators', function (): void {
        $stage = new ValidationStage();

        expect($stage)->toBeInstanceOf(ValidationStage::class)
            ->and($stage->getName())->toBe('validation');
    });

    it('can be instantiated with validators', function (): void {
        $validator = Mockery::mock(CartValidatorInterface::class);
        $stage = new ValidationStage([$validator]);

        expect($stage)->toBeInstanceOf(ValidationStage::class);
    });

    it('can add a validator', function (): void {
        $stage = new ValidationStage();
        $validator = Mockery::mock(CartValidatorInterface::class);

        $result = $stage->addValidator($validator);

        expect($result)->toBe($stage);
    });

    it('always should execute', function (): void {
        $stage = new ValidationStage();
        $cart = new Cart($this->storage, 'cart-123');

        expect($stage->shouldExecute($cart, []))->toBeTrue();
    });

    it('fails validation for empty cart', function (): void {
        $stage = new ValidationStage();
        $cart = new Cart($this->storage, 'cart-123');

        $result = $stage->execute($cart, []);

        expect($result)->toBeInstanceOf(StageResult::class)
            ->and($result->success)->toBeFalse()
            ->and($result->message)->toBe('Cart is empty');
    });

    it('passes validation for non-empty cart without validators', function (): void {
        $stage = new ValidationStage();
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product 1', 1000, 3);
        $cart->add('item-2', 'Product 2', 2000, 2);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Cart validated successfully')
            ->and($result->data)->toHaveKey('validated_at')
            ->and($result->data['item_count'])->toBe(2)
            ->and($result->data['total_quantity'])->toBe(5);
    });

    it('runs all validators in order of priority', function (): void {
        $executionOrder = [];

        $validator1 = Mockery::mock(CartValidatorInterface::class);
        $validator1->shouldReceive('getType')->andReturn('priority_10');
        $validator1->shouldReceive('getPriority')->andReturn(10);
        $validator1->shouldReceive('validateCart')->andReturnUsing(function () use (&$executionOrder) {
            $executionOrder[] = 'priority_10';

            return new CartValidationResult(true, 'Valid');
        });

        $validator2 = Mockery::mock(CartValidatorInterface::class);
        $validator2->shouldReceive('getType')->andReturn('priority_1');
        $validator2->shouldReceive('getPriority')->andReturn(1);
        $validator2->shouldReceive('validateCart')->andReturnUsing(function () use (&$executionOrder) {
            $executionOrder[] = 'priority_1';

            return new CartValidationResult(true, 'Valid');
        });

        $validator3 = Mockery::mock(CartValidatorInterface::class);
        $validator3->shouldReceive('getType')->andReturn('priority_5');
        $validator3->shouldReceive('getPriority')->andReturn(5);
        $validator3->shouldReceive('validateCart')->andReturnUsing(function () use (&$executionOrder) {
            $executionOrder[] = 'priority_5';

            return new CartValidationResult(true, 'Valid');
        });

        $stage = new ValidationStage([$validator1, $validator2, $validator3]);

        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product 1', 1000, 2);
        $cart->add('item-2', 'Product 2', 2000, 2);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($executionOrder)->toBe(['priority_1', 'priority_5', 'priority_10']);
    });

    it('fails when any validator fails', function (): void {
        $validator1 = Mockery::mock(CartValidatorInterface::class);
        $validator1->shouldReceive('getType')->andReturn('inventory');
        $validator1->shouldReceive('getPriority')->andReturn(1);
        $validator1->shouldReceive('validateCart')->andReturn(new CartValidationResult(false, 'Item out of stock'));

        $stage = new ValidationStage([$validator1]);

        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product 1', 1000);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Cart validation failed')
            ->and($result->errors)->toHaveKey('inventory')
            ->and($result->errors['inventory'])->toBe('Item out of stock');
    });

    it('collects all validation errors', function (): void {
        $validator1 = Mockery::mock(CartValidatorInterface::class);
        $validator1->shouldReceive('getType')->andReturn('inventory');
        $validator1->shouldReceive('getPriority')->andReturn(1);
        $validator1->shouldReceive('validateCart')->andReturn(new CartValidationResult(false, 'Item out of stock'));

        $validator2 = Mockery::mock(CartValidatorInterface::class);
        $validator2->shouldReceive('getType')->andReturn('price');
        $validator2->shouldReceive('getPriority')->andReturn(2);
        $validator2->shouldReceive('validateCart')->andReturn(new CartValidationResult(false, 'Price changed'));

        $stage = new ValidationStage([$validator1, $validator2]);

        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product 1', 1000);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->errors)->toHaveKeys(['inventory', 'price']);
    });

    it('does not support rollback', function (): void {
        $stage = new ValidationStage();

        expect($stage->supportsRollback())->toBeFalse();
    });

    it('rollback does nothing', function (): void {
        $stage = new ValidationStage();
        $cart = new Cart($this->storage, 'cart-123');

        $stage->rollback($cart, []);

        expect(true)->toBeTrue(); // No exception thrown
    });
});
