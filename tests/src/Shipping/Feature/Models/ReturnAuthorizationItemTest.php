<?php

declare(strict_types=1);

use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\ReturnAuthorizationItem;

describe('ReturnAuthorizationItem Model', function (): void {
    it('can create a return authorization item with required fields', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $item = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Test Item',
        ]);

        expect($item)->toBeInstanceOf(ReturnAuthorizationItem::class);
        expect($item->id)->toBeString();
        expect($item->return_authorization_id)->toBe($rma->id);
        expect($item->name)->toBe('Test Item');
        expect($item->quantity_requested)->toBe(1);
        expect($item->quantity_approved)->toBe(0);
        expect($item->quantity_received)->toBe(0);
    });

    it('can create return authorization item with all fields', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $item = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'original_item_id' => 'item-123',
            'original_item_type' => 'App\\Models\\Product',
            'sku' => 'TEST-SKU-001',
            'name' => 'Premium Widget',
            'quantity_requested' => 3,
            'quantity_approved' => 2,
            'quantity_received' => 1,
            'reason' => 'defective',
            'condition' => 'damaged',
            'metadata' => ['notes' => 'Box was crushed'],
        ]);

        expect($item->original_item_id)->toBe('item-123');
        expect($item->original_item_type)->toBe('App\\Models\\Product');
        expect($item->sku)->toBe('TEST-SKU-001');
        expect($item->name)->toBe('Premium Widget');
        expect($item->quantity_requested)->toBe(3);
        expect($item->quantity_approved)->toBe(2);
        expect($item->quantity_received)->toBe(1);
        expect($item->reason)->toBe('defective');
        expect($item->condition)->toBe('damaged');
        expect($item->metadata)->toBe(['notes' => 'Box was crushed']);
    });

    it('belongs to a return authorization', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $item = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Test Item',
        ]);

        expect($item->returnAuthorization)->toBeInstanceOf(ReturnAuthorization::class);
        expect($item->returnAuthorization->id)->toBe($rma->id);
    });

    it('checks if item is fully approved', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $fullyApproved = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Fully Approved Item',
            'quantity_requested' => 2,
            'quantity_approved' => 2,
        ]);

        $partiallyApproved = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Partially Approved Item',
            'quantity_requested' => 3,
            'quantity_approved' => 1,
        ]);

        $notApproved = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Not Approved Item',
            'quantity_requested' => 1,
            'quantity_approved' => 0,
        ]);

        expect($fullyApproved->isFullyApproved())->toBeTrue();
        expect($partiallyApproved->isFullyApproved())->toBeFalse();
        expect($notApproved->isFullyApproved())->toBeFalse();
    });

    it('checks if item is fully received', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $fullyReceived = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Fully Received Item',
            'quantity_approved' => 2,
            'quantity_received' => 2,
        ]);

        $partiallyReceived = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Partially Received Item',
            'quantity_approved' => 3,
            'quantity_received' => 1,
        ]);

        $notReceived = ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Not Received Item',
            'quantity_approved' => 1,
            'quantity_received' => 0,
        ]);

        expect($fullyReceived->isFullyReceived())->toBeTrue();
        expect($partiallyReceived->isFullyReceived())->toBeFalse();
        expect($notReceived->isFullyReceived())->toBeFalse();
    });
});
