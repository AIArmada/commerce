<?php

declare(strict_types=1);

use AIArmada\Cart\Commands\AddItemCommand;

describe('AddItemCommand', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $command = new AddItemCommand(
            identifier: 'user-123',
            instance: 'shopping',
            itemId: 'item-001',
            itemName: 'Test Product',
            priceInCents: 1999
        );

        expect($command->identifier)->toBe('user-123')
            ->and($command->instance)->toBe('shopping')
            ->and($command->itemId)->toBe('item-001')
            ->and($command->itemName)->toBe('Test Product')
            ->and($command->priceInCents)->toBe(1999)
            ->and($command->quantity)->toBe(1)
            ->and($command->attributes)->toBeEmpty()
            ->and($command->associatedModel)->toBeNull()
            ->and($command->associatedModelId)->toBeNull();
    });

    it('can be instantiated with all parameters', function (): void {
        $command = new AddItemCommand(
            identifier: 'user-123',
            instance: 'shopping',
            itemId: 'item-001',
            itemName: 'Test Product',
            priceInCents: 2999,
            quantity: 3,
            attributes: ['color' => 'blue', 'size' => 'M'],
            associatedModel: 'App\\Models\\Product',
            associatedModelId: 'prod-uuid-123'
        );

        expect($command->quantity)->toBe(3)
            ->and($command->attributes)->toBe(['color' => 'blue', 'size' => 'M'])
            ->and($command->associatedModel)->toBe('App\\Models\\Product')
            ->and($command->associatedModelId)->toBe('prod-uuid-123');
    });

    it('creates command from array', function (): void {
        $command = AddItemCommand::fromArray([
            'identifier' => 'user-456',
            'instance' => 'wishlist',
            'item_id' => 'item-002',
            'item_name' => 'Another Product',
            'price_in_cents' => 4999,
            'quantity' => 2,
            'attributes' => ['size' => 'L'],
            'associated_model' => 'App\\Models\\Product',
            'associated_model_id' => 'prod-uuid-456',
        ]);

        expect($command->identifier)->toBe('user-456')
            ->and($command->instance)->toBe('wishlist')
            ->and($command->itemId)->toBe('item-002')
            ->and($command->itemName)->toBe('Another Product')
            ->and($command->priceInCents)->toBe(4999)
            ->and($command->quantity)->toBe(2)
            ->and($command->attributes)->toBe(['size' => 'L'])
            ->and($command->associatedModel)->toBe('App\\Models\\Product')
            ->and($command->associatedModelId)->toBe('prod-uuid-456');
    });

    it('creates command from array with defaults', function (): void {
        $command = AddItemCommand::fromArray([
            'identifier' => 'user-789',
            'item_id' => 'item-003',
            'item_name' => 'Simple Product',
            'price_in_cents' => 999,
        ]);

        expect($command->instance)->toBe('default')
            ->and($command->quantity)->toBe(1)
            ->and($command->attributes)->toBeEmpty()
            ->and($command->associatedModel)->toBeNull()
            ->and($command->associatedModelId)->toBeNull();
    });

    it('converts to array', function (): void {
        $command = new AddItemCommand(
            identifier: 'user-123',
            instance: 'shopping',
            itemId: 'item-001',
            itemName: 'Test Product',
            priceInCents: 2999,
            quantity: 3,
            attributes: ['color' => 'red'],
            associatedModel: 'App\\Models\\Product',
            associatedModelId: 'prod-123'
        );

        $array = $command->toArray();

        expect($array)->toBeArray()
            ->and($array['identifier'])->toBe('user-123')
            ->and($array['instance'])->toBe('shopping')
            ->and($array['item_id'])->toBe('item-001')
            ->and($array['item_name'])->toBe('Test Product')
            ->and($array['price_in_cents'])->toBe(2999)
            ->and($array['quantity'])->toBe(3)
            ->and($array['attributes'])->toBe(['color' => 'red'])
            ->and($array['associated_model'])->toBe('App\\Models\\Product')
            ->and($array['associated_model_id'])->toBe('prod-123');
    });

    it('round trips through array conversion', function (): void {
        $original = new AddItemCommand(
            identifier: 'user-roundtrip',
            instance: 'cart',
            itemId: 'item-rt',
            itemName: 'Roundtrip Product',
            priceInCents: 5000,
            quantity: 5,
            attributes: ['key' => 'value'],
            associatedModel: 'App\\Models\\Item',
            associatedModelId: 'item-uuid'
        );

        $fromArray = AddItemCommand::fromArray($original->toArray());

        expect($fromArray->identifier)->toBe($original->identifier)
            ->and($fromArray->instance)->toBe($original->instance)
            ->and($fromArray->itemId)->toBe($original->itemId)
            ->and($fromArray->itemName)->toBe($original->itemName)
            ->and($fromArray->priceInCents)->toBe($original->priceInCents)
            ->and($fromArray->quantity)->toBe($original->quantity)
            ->and($fromArray->attributes)->toBe($original->attributes)
            ->and($fromArray->associatedModel)->toBe($original->associatedModel)
            ->and($fromArray->associatedModelId)->toBe($original->associatedModelId);
    });
});
