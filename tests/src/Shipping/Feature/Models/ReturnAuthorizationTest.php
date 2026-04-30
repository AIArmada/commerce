<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\ReturnReason;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\ReturnAuthorizationItem;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

describe('ReturnAuthorization Model', function (): void {
    it('can create a return authorization with required fields', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($rma)->toBeInstanceOf(ReturnAuthorization::class);
        expect($rma->id)->toBeString();
        expect($rma->rma_number)->toStartWith('RMA-');
        expect($rma->owner_type)->toBe('TestOwner');
        expect($rma->owner_id)->toBe('test-owner-123');
        expect($rma->type)->toBe('refund');
        expect($rma->reason)->toBe('defective');
        expect($rma->status)->toBe('pending');
    });

    it('generates unique RMA numbers', function (): void {
        $rma1 = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $rma2 = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'exchange',
            'reason' => 'wrong_item',
        ]);

        expect($rma1->rma_number)->not->toBe($rma2->rma_number);
        expect($rma1->rma_number)->toStartWith('RMA-');
        expect($rma2->rma_number)->toStartWith('RMA-');
    });

    it('can create return authorization with all fields', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'rma_number' => 'RMA-CUSTOM-001',
            'original_shipment_id' => 'shipment-123',
            'order_reference' => 'ORDER-456',
            'customer_id' => 'customer-789',
            'status' => 'approved',
            'type' => 'refund',
            'reason' => 'defective',
            'reason_details' => 'Item arrived damaged',
            'approved_by' => 'admin@example.com',
            'approved_at' => now(),
            'received_at' => now()->addDay(),
            'completed_at' => now()->addDays(2),
            'expires_at' => now()->addDays(30),
            'metadata' => ['priority' => 'high'],
        ]);

        expect($rma->rma_number)->toBe('RMA-CUSTOM-001');
        expect($rma->original_shipment_id)->toBe('shipment-123');
        expect($rma->order_reference)->toBe('ORDER-456');
        expect($rma->customer_id)->toBe('customer-789');
        expect($rma->status)->toBe('approved');
        expect($rma->type)->toBe('refund');
        expect($rma->reason)->toBe('defective');
        expect($rma->reason_details)->toBe('Item arrived damaged');
        expect($rma->approved_by)->toBe('admin@example.com');
        expect($rma->approved_at)->not->toBeNull();
        expect($rma->received_at)->not->toBeNull();
        expect($rma->completed_at)->not->toBeNull();
        expect($rma->expires_at)->not->toBeNull();
        expect($rma->metadata)->toBe(['priority' => 'high']);
    });

    it('has correct relationships', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'SHIP-001',
            'carrier_code' => 'fedex',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'original_shipment_id' => $shipment->id,
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($rma->originalShipment)->toBeInstanceOf(Shipment::class);
        expect($rma->originalShipment->id)->toBe($shipment->id);
        expect($rma->items())->toBeInstanceOf(HasMany::class);
    });

    it('has status helper methods', function (): void {
        $pending = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $approved = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'approved',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $completed = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'completed',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($pending->isPending())->toBeTrue();
        expect($pending->isApproved())->toBeFalse();
        expect($pending->isCompleted())->toBeFalse();

        expect($approved->isPending())->toBeFalse();
        expect($approved->isApproved())->toBeTrue();
        expect($approved->isCompleted())->toBeFalse();

        expect($completed->isPending())->toBeFalse();
        expect($completed->isApproved())->toBeFalse();
        expect($completed->isCompleted())->toBeTrue();
    });

    it('checks if return authorization is expired', function (): void {
        $expired = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
            'expires_at' => now()->subDay(),
        ]);

        $notExpired = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
            'expires_at' => now()->addDay(),
        ]);

        $noExpiry = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'approved', // Not pending
            'type' => 'refund',
            'reason' => 'defective',
            'expires_at' => now()->subDay(),
        ]);

        expect($expired->isExpired())->toBeTrue();
        expect($notExpired->isExpired())->toBeFalse();
        expect($noExpiry->isExpired())->toBeFalse();
    });

    it('returns reason enum', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $invalidRma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'invalid_reason',
        ]);

        expect($rma->getReasonEnum())->toBe(ReturnReason::Defective);
        expect($invalidRma->getReasonEnum())->toBeNull();
    });

    it('has pending and approved scopes', function (): void {
        ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'approved',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'completed',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $pending = ReturnAuthorization::pending()->get();
        $approved = ReturnAuthorization::approved()->get();

        expect($pending)->toHaveCount(1);
        expect($pending->first()->status)->toBe('pending');

        expect($approved)->toHaveCount(1);
        expect($approved->first()->status)->toBe('approved');
    });

    it('cascades delete to items', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Item 1',
            'quantity' => 1,
            'reason' => 'defective',
        ]);

        ReturnAuthorizationItem::create([
            'return_authorization_id' => $rma->id,
            'name' => 'Item 2',
            'quantity' => 2,
            'reason' => 'wrong_size',
        ]);

        expect(ReturnAuthorizationItem::count())->toBe(2);

        $rma->delete();

        expect(ReturnAuthorizationItem::count())->toBe(0);
    });

    it('has returnShipment relationship', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        // Create a return shipment associated with this RMA
        $returnShipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'RETURN-001',
            'carrier_code' => 'fedex',
            'shippable_type' => ReturnAuthorization::class,
            'shippable_id' => $rma->id,
            'origin_address' => ['name' => 'Customer'],
            'destination_address' => ['name' => 'Warehouse'],
        ]);

        $rma->refresh();

        expect($rma->returnShipment())->toBeInstanceOf(MorphOne::class);
        expect($rma->returnShipment)->toBeInstanceOf(Shipment::class);
        expect($rma->returnShipment->id)->toBe($returnShipment->id);
    });

    it('returns null when no return shipment exists', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($rma->returnShipment)->toBeNull();
    });

    it('has isRejected status helper', function (): void {
        $rejected = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'rejected',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $pending = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($rejected->isRejected())->toBeTrue();
        expect($pending->isRejected())->toBeFalse();
    });

    it('has isReceived status helper', function (): void {
        $received = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'received',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $pending = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($received->isReceived())->toBeTrue();
        expect($pending->isReceived())->toBeFalse();
    });

    it('has isCancelled status helper', function (): void {
        $cancelled = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'cancelled',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        $pending = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
        ]);

        expect($cancelled->isCancelled())->toBeTrue();
        expect($pending->isCancelled())->toBeFalse();
    });

    it('returns false for isExpired when expires_at is null', function (): void {
        $rma = ReturnAuthorization::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'status' => 'pending',
            'type' => 'refund',
            'reason' => 'defective',
            'expires_at' => null,
        ]);

        expect($rma->isExpired())->toBeFalse();
    });
});
