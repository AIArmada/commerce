<?php

declare(strict_types=1);

use AIArmada\Cart\Commands\ApplyConditionCommand;
use AIArmada\Cart\Commands\ClearCartCommand;
use AIArmada\Cart\Commands\RemoveItemCommand;
use AIArmada\Cart\Commands\UpdateItemQuantityCommand;

describe('ApplyConditionCommand', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $command = new ApplyConditionCommand(
            identifier: 'user-123',
            instance: 'shopping',
            conditionName: 'Discount 10%',
            conditionType: 'discount',
            value: '-10%'
        );

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('shopping')
            ->and($command->conditionName)->toBe('Discount 10%')
            ->and($command->conditionType)->toBe('discount')
            ->and($command->value)->toBe('-10%')
            ->and($command->target)->toBe('cart@cart_subtotal/aggregate')
            ->and($command->order)->toBe(0)
            ->and($command->attributes)->toBeEmpty();
    });

    it('can be instantiated with all parameters', function (): void {
        $command = new ApplyConditionCommand(
            identifier: 'user-123',
            instance: 'shopping',
            conditionName: 'Shipping',
            conditionType: 'shipping',
            value: '+500',
            target: 'cart@shipping/add',
            order: 5,
            attributes: ['description' => 'Standard shipping']
        );

        expect($command->target)->toBe('cart@shipping/add')
            ->and($command->order)->toBe(5)
            ->and($command->attributes)->toBe(['description' => 'Standard shipping']);
    });

    it('creates command from array', function (): void {
        $command = ApplyConditionCommand::fromArray([
            'identifier' => 'user-456',
            'instance' => 'wishlist',
            'condition_name' => 'Tax',
            'condition_type' => 'tax',
            'value' => '+8%',
            'target' => 'cart@tax/rate',
            'order' => 10,
            'attributes' => ['rate' => 'CA'],
        ]);

        expect($command->identifier)->toBe('user-456')
            ->and($command->conditionName)->toBe('Tax')
            ->and($command->conditionType)->toBe('tax')
            ->and($command->value)->toBe('+8%')
            ->and($command->target)->toBe('cart@tax/rate')
            ->and($command->order)->toBe(10);
    });

    it('creates command from array with defaults', function (): void {
        $command = ApplyConditionCommand::fromArray([
            'identifier' => 'user-789',
            'condition_name' => 'Coupon',
            'condition_type' => 'coupon',
            'value' => '-500',
        ]);

        expect($command->instance)->toBe('default')
            ->and($command->target)->toBe('cart@cart_subtotal/aggregate')
            ->and($command->order)->toBe(0)
            ->and($command->attributes)->toBeEmpty();
    });

    it('converts to array', function (): void {
        $command = new ApplyConditionCommand(
            identifier: 'user-123',
            instance: 'cart',
            conditionName: 'SAVE20',
            conditionType: 'coupon',
            value: '-20%',
            target: 'cart@coupon/apply',
            order: 2,
            attributes: ['code' => 'SAVE20']
        );

        $array = $command->toArray();

        expect($array)->toBeArray()
            ->and($array['identifier'])->toBe('user-123')
            ->and($array['instance'])->toBe('cart')
            ->and($array['condition_name'])->toBe('SAVE20')
            ->and($array['condition_type'])->toBe('coupon')
            ->and($array['value'])->toBe('-20%')
            ->and($array['target'])->toBe('cart@coupon/apply')
            ->and($array['order'])->toBe(2)
            ->and($array['attributes'])->toBe(['code' => 'SAVE20']);
    });
});

describe('ClearCartCommand', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $command = new ClearCartCommand(identifier: 'user-123');

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('default');
    });

    it('can be instantiated with all parameters', function (): void {
        $command = new ClearCartCommand(identifier: 'user-123', instance: 'shopping');

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('shopping');
    });

    it('creates command from array', function (): void {
        $command = ClearCartCommand::fromArray([
            'identifier' => 'user-456',
            'instance' => 'wishlist',
        ]);

        expect($command->identifier)->toBe('user-456')
            ->and($command->instance)->toBe('wishlist');
    });

    it('creates command from array with defaults', function (): void {
        $command = ClearCartCommand::fromArray([
            'identifier' => 'user-789',
        ]);

        expect($command->instance)->toBe('default');
    });

    it('converts to array', function (): void {
        $command = new ClearCartCommand(identifier: 'user-123', instance: 'shopping');

        $array = $command->toArray();

        expect($array)->toBe([
            'identifier' => 'user-123',
            'instance' => 'shopping',
        ]);
    });
});

describe('RemoveItemCommand', function (): void {
    it('can be instantiated', function (): void {
        $command = new RemoveItemCommand(
            identifier: 'user-123',
            instance: 'shopping',
            itemId: 'item-001'
        );

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('shopping')
            ->and($command->itemId)->toBe('item-001');
    });

    it('creates command from array', function (): void {
        $command = RemoveItemCommand::fromArray([
            'identifier' => 'user-456',
            'instance' => 'wishlist',
            'item_id' => 'item-002',
        ]);

        expect($command->identifier)->toBe('user-456')
            ->and($command->instance)->toBe('wishlist')
            ->and($command->itemId)->toBe('item-002');
    });

    it('creates command from array with default instance', function (): void {
        $command = RemoveItemCommand::fromArray([
            'identifier' => 'user-789',
            'item_id' => 'item-003',
        ]);

        expect($command->instance)->toBe('default');
    });

    it('converts to array', function (): void {
        $command = new RemoveItemCommand(
            identifier: 'user-123',
            instance: 'cart',
            itemId: 'item-100'
        );

        $array = $command->toArray();

        expect($array)->toBe([
            'identifier' => 'user-123',
            'instance' => 'cart',
            'item_id' => 'item-100',
        ]);
    });
});

describe('UpdateItemQuantityCommand', function (): void {
    it('can be instantiated', function (): void {
        $command = new UpdateItemQuantityCommand(
            identifier: 'user-123',
            instance: 'shopping',
            itemId: 'item-001',
            newQuantity: 5
        );

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('shopping')
            ->and($command->itemId)->toBe('item-001')
            ->and($command->newQuantity)->toBe(5);
    });

    it('creates command from array', function (): void {
        $command = UpdateItemQuantityCommand::fromArray([
            'identifier' => 'user-456',
            'instance' => 'wishlist',
            'item_id' => 'item-002',
            'new_quantity' => 10,
        ]);

        expect($command->identifier)->toBe('user-456')
            ->and($command->instance)->toBe('wishlist')
            ->and($command->itemId)->toBe('item-002')
            ->and($command->newQuantity)->toBe(10);
    });

    it('creates command from array with default instance', function (): void {
        $command = UpdateItemQuantityCommand::fromArray([
            'identifier' => 'user-789',
            'item_id' => 'item-003',
            'new_quantity' => 3,
        ]);

        expect($command->instance)->toBe('default');
    });

    it('converts to array', function (): void {
        $command = new UpdateItemQuantityCommand(
            identifier: 'user-123',
            instance: 'cart',
            itemId: 'item-100',
            newQuantity: 7
        );

        $array = $command->toArray();

        expect($array)->toBe([
            'identifier' => 'user-123',
            'instance' => 'cart',
            'item_id' => 'item-100',
            'new_quantity' => 7,
        ]);
    });

    it('casts quantity to integer', function (): void {
        $command = UpdateItemQuantityCommand::fromArray([
            'identifier' => 'user-123',
            'instance' => 'cart',
            'item_id' => 'item-001',
            'new_quantity' => '15',
        ]);

        expect($command->newQuantity)->toBe(15);
    });
});
