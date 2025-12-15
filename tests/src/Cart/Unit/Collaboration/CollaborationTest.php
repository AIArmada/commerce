<?php

declare(strict_types=1);

use AIArmada\Cart\Collaboration\Collaborator;
use AIArmada\Cart\Collaboration\CRDTOperation;

describe('Collaborator', function (): void {
    it('can be instantiated with all parameters', function (): void {
        $joinedAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $collaborator = new Collaborator(
            userId: 'user-123',
            email: 'user@example.com',
            role: 'editor',
            status: 'active',
            joinedAt: $joinedAt,
            invitationToken: 'token-abc'
        );

        expect($collaborator->userId)->toBe('user-123')
            ->and($collaborator->email)->toBe('user@example.com')
            ->and($collaborator->role)->toBe('editor')
            ->and($collaborator->status)->toBe('active')
            ->and($collaborator->joinedAt)->toBe($joinedAt)
            ->and($collaborator->invitationToken)->toBe('token-abc');
    });

    it('can be created from array', function (): void {
        $collaborator = Collaborator::fromArray([
            'user_id' => 'user-456',
            'email' => 'test@example.com',
            'role' => 'viewer',
            'status' => 'pending',
            'joined_at' => '2024-01-15 12:00:00',
            'invitation_token' => 'invite-xyz',
        ]);

        expect($collaborator->userId)->toBe('user-456')
            ->and($collaborator->email)->toBe('test@example.com')
            ->and($collaborator->role)->toBe('viewer')
            ->and($collaborator->status)->toBe('pending')
            ->and($collaborator->invitationToken)->toBe('invite-xyz');
    });

    it('uses default values when creating from array', function (): void {
        $collaborator = Collaborator::fromArray([]);

        expect($collaborator->userId)->toBeNull()
            ->and($collaborator->email)->toBeNull()
            ->and($collaborator->role)->toBe('viewer')
            ->and($collaborator->status)->toBe('pending')
            ->and($collaborator->joinedAt)->toBeNull()
            ->and($collaborator->invitationToken)->toBeNull();
    });

    it('detects active status', function (): void {
        $active = new Collaborator('user-1', 'a@b.com', 'editor', 'active');
        $pending = new Collaborator('user-2', 'b@c.com', 'editor', 'pending');

        expect($active->isActive())->toBeTrue()
            ->and($pending->isActive())->toBeFalse();
    });

    it('detects pending status', function (): void {
        $pending = new Collaborator('user-1', 'a@b.com', 'editor', 'pending');
        $active = new Collaborator('user-2', 'b@c.com', 'editor', 'active');

        expect($pending->isPending())->toBeTrue()
            ->and($active->isPending())->toBeFalse();
    });

    it('checks if can edit for editor role', function (): void {
        $editor = new Collaborator('user-1', 'a@b.com', 'editor', 'active');
        $admin = new Collaborator('user-2', 'b@c.com', 'admin', 'active');
        $viewer = new Collaborator('user-3', 'c@d.com', 'viewer', 'active');

        expect($editor->canEdit())->toBeTrue()
            ->and($admin->canEdit())->toBeTrue()
            ->and($viewer->canEdit())->toBeFalse();
    });

    it('converts to array', function (): void {
        $joinedAt = new DateTimeImmutable('2024-01-15 10:30:00');

        $collaborator = new Collaborator(
            userId: 'user-123',
            email: 'user@example.com',
            role: 'editor',
            status: 'active',
            joinedAt: $joinedAt,
            invitationToken: 'token-abc'
        );

        $array = $collaborator->toArray();

        expect($array['user_id'])->toBe('user-123')
            ->and($array['email'])->toBe('user@example.com')
            ->and($array['role'])->toBe('editor')
            ->and($array['status'])->toBe('active')
            ->and($array['joined_at'])->toBe('2024-01-15 10:30:00')
            ->and($array['invitation_token'])->toBe('token-abc');
    });

    it('handles null joinedAt in toArray', function (): void {
        $collaborator = new Collaborator(
            userId: 'user-123',
            email: 'user@example.com',
            role: 'viewer',
            status: 'pending'
        );

        $array = $collaborator->toArray();

        expect($array['joined_at'])->toBeNull();
    });
});

describe('CRDTOperation', function (): void {
    it('can be instantiated with all parameters', function (): void {
        $timestamp = new DateTimeImmutable('2024-01-15 10:00:00');

        $operation = new CRDTOperation(
            id: 'op-123',
            type: 'add',
            cartId: 'cart-456',
            userId: 'user-789',
            itemId: 'item-001',
            data: ['quantity' => 2, 'price' => 1000],
            vectorClock: ['node-1' => 1, 'node-2' => 2],
            timestamp: $timestamp
        );

        expect($operation->id)->toBe('op-123')
            ->and($operation->type)->toBe('add')
            ->and($operation->cartId)->toBe('cart-456')
            ->and($operation->userId)->toBe('user-789')
            ->and($operation->itemId)->toBe('item-001')
            ->and($operation->data)->toBe(['quantity' => 2, 'price' => 1000])
            ->and($operation->vectorClock)->toBe(['node-1' => 1, 'node-2' => 2])
            ->and($operation->timestamp)->toBe($timestamp);
    });

    it('can be created from array', function (): void {
        $operation = CRDTOperation::fromArray([
            'id' => 'op-456',
            'type' => 'update',
            'cart_id' => 'cart-123',
            'user_id' => 'user-456',
            'item_id' => 'item-789',
            'data' => ['quantity' => 5],
            'vector_clock' => ['node-a' => 3],
            'timestamp' => '2024-01-15 12:30:00',
        ]);

        expect($operation->id)->toBe('op-456')
            ->and($operation->type)->toBe('update')
            ->and($operation->cartId)->toBe('cart-123')
            ->and($operation->userId)->toBe('user-456')
            ->and($operation->itemId)->toBe('item-789')
            ->and($operation->data)->toBe(['quantity' => 5])
            ->and($operation->vectorClock)->toBe(['node-a' => 3]);
    });

    it('uses default values for optional fields when creating from array', function (): void {
        $operation = CRDTOperation::fromArray([
            'id' => 'op-789',
            'type' => 'remove',
            'cart_id' => 'cart-abc',
            'user_id' => 'user-def',
            'item_id' => 'item-ghi',
            'timestamp' => '2024-01-15 14:00:00',
        ]);

        expect($operation->data)->toBe([])
            ->and($operation->vectorClock)->toBe([]);
    });

    it('converts to array', function (): void {
        $timestamp = new DateTimeImmutable('2024-01-15 10:30:00');

        $operation = new CRDTOperation(
            id: 'op-001',
            type: 'add',
            cartId: 'cart-002',
            userId: 'user-003',
            itemId: 'item-004',
            data: ['name' => 'Product'],
            vectorClock: ['x' => 1],
            timestamp: $timestamp
        );

        $array = $operation->toArray();

        expect($array['id'])->toBe('op-001')
            ->and($array['type'])->toBe('add')
            ->and($array['cart_id'])->toBe('cart-002')
            ->and($array['user_id'])->toBe('user-003')
            ->and($array['item_id'])->toBe('item-004')
            ->and($array['data'])->toBe(['name' => 'Product'])
            ->and($array['vector_clock'])->toBe(['x' => 1])
            ->and($array['timestamp'])->toBe('2024-01-15 10:30:00');
    });

    it('supports all operation types', function (string $type): void {
        $operation = new CRDTOperation(
            id: 'op-1',
            type: $type,
            cartId: 'cart-1',
            userId: 'user-1',
            itemId: 'item-1',
            data: [],
            vectorClock: [],
            timestamp: new DateTimeImmutable
        );

        expect($operation->type)->toBe($type);
    })->with(['add', 'update', 'remove']);
});
