<?php

declare(strict_types=1);

use AIArmada\Cart\Broadcasting\CartChannel;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('CartChannel owner scoping', function (): void {
    it('rejects collaborative cart join across tenant boundary', function (): void {
        $ownerA = createUserWithRoles();
        $ownerB = createUserWithRoles();

        $cartId = (string) Str::uuid();
        $tableName = config('cart.database.table', 'carts');

        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $connection->table($tableName)->insert([
            'id' => $cartId,
            'identifier' => 'collab-test',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'instance' => 'default',
            'items' => null,
            'conditions' => null,
            'metadata' => null,
            'version' => 1,
            'expires_at' => null,
            'is_collaborative' => true,
            'owner_user_id' => (string) $ownerA->getKey(),
            'collaborators' => json_encode([]),
            'max_collaborators' => 5,
            'collaboration_mode' => 'edit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $baseStorage = new DatabaseStorage($connection, $tableName);

        $channelInOwnerA = new CartChannel($connection, $baseStorage->withOwner($ownerA));
        $channelInOwnerB = new CartChannel($connection, $baseStorage->withOwner($ownerB));

        expect($channelInOwnerA->join($ownerA, $cartId))
            ->toBeArray()
            ->toHaveKey('role', 'owner');

        expect($channelInOwnerB->join($ownerA, $cartId))->toBeFalse();
    });
});
