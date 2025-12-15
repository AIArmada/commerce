<?php

declare(strict_types=1);

use AIArmada\Cart\Collaboration\CartCRDT;
use AIArmada\Cart\Collaboration\CRDTOperation;

describe('CartCRDT', function (): void {
    beforeEach(function (): void {
        $this->crdt = new CartCRDT;
    });

    it('can be instantiated', function (): void {
        expect($this->crdt)->toBeInstanceOf(CartCRDT::class);
    });

    it('creates add operation', function (): void {
        $operation = $this->crdt->createAddOperation(
            cartId: 'cart-123',
            userId: 'user-456',
            itemId: 'item-789',
            data: ['name' => 'Product', 'price' => 1000, 'quantity' => 1]
        );

        expect($operation)->toBeInstanceOf(CRDTOperation::class)
            ->and($operation->cartId)->toBe('cart-123')
            ->and($operation->userId)->toBe('user-456')
            ->and($operation->itemId)->toBe('item-789')
            ->and($operation->type)->toBe('add');
    });

    it('creates update operation', function (): void {
        $operation = $this->crdt->createUpdateOperation(
            cartId: 'cart-123',
            userId: 'user-456',
            itemId: 'item-789',
            data: ['quantity' => 5]
        );

        expect($operation)->toBeInstanceOf(CRDTOperation::class)
            ->and($operation->type)->toBe('update');
    });

    it('creates remove operation', function (): void {
        $operation = $this->crdt->createRemoveOperation(
            cartId: 'cart-123',
            userId: 'user-456',
            itemId: 'item-789'
        );

        expect($operation)->toBeInstanceOf(CRDTOperation::class)
            ->and($operation->type)->toBe('remove');
    });

    it('gets vector clock for cart', function (): void {
        $clock = $this->crdt->getVectorClock('cart-vector-test');

        expect($clock)->toBeArray();
    });

    it('gets operations for cart', function (): void {
        $operations = $this->crdt->getOperations('cart-ops-test');

        expect($operations)->toBeArray();
    });

    it('replays operations to rebuild state', function (): void {
        $state = $this->crdt->replay('cart-replay-test');

        expect($state)->toBeArray();
    });

    it('applies an operation', function (): void {
        $operation = $this->crdt->createAddOperation(
            'cart-apply-test',
            'user-1',
            'item-1',
            ['quantity' => 1]
        );

        $result = $this->crdt->apply($operation);

        expect($result)->toBeBool();
    });

    it('merges remote operations', function (): void {
        $operation1 = $this->crdt->createAddOperation(
            'cart-merge-test',
            'user-1',
            'item-1',
            ['quantity' => 1]
        );

        $result = $this->crdt->merge('cart-merge-test', [$operation1]);

        expect($result)->toBeArray();
    });
});
