<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\States\Cancelled;
use AIArmada\Inventory\States\Expired;
use AIArmada\Inventory\States\Fulfilled;
use AIArmada\Inventory\States\PartiallyFulfilled;
use AIArmada\Inventory\States\Pending;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('InventoryBackorder', function (): void {
    it('get table returns correct table name', function (): void {
        $backorder = new InventoryBackorder;
        expect($backorder->getTable())->toBe('inventory_backorders');
    });

    it('inventoryable relationship', function (): void {
        $backorder = new InventoryBackorder;
        $relation = $backorder->inventoryable();
        expect($relation)->toBeInstanceOf(MorphTo::class);
    });

    it('location relationship', function (): void {
        $backorder = new InventoryBackorder;
        $relation = $backorder->location();
        expect($relation)->toBeInstanceOf(BelongsTo::class);
    });

    it('scope open filters correctly', function (): void {
        InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '1',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);
        InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '2',
            'status' => PartiallyFulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);
        InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '3',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $open = InventoryBackorder::open()->get();
        expect($open)->toHaveCount(2);
    });

    it('scope pending filters correctly', function (): void {
        InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '4',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);
        InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '5',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $pending = InventoryBackorder::pending()->get();
        expect($pending)->toHaveCount(1);
        expect($pending->first()->status)->toBeInstanceOf(Pending::class);
    });

    it('is closed', function (): void {
        $fulfilled = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '6',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 10,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $pending = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '7',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        expect($fulfilled->isClosed())->toBeTrue();
        expect($pending->isClosed())->toBeFalse();
    });

    it('is overdue', function (): void {
        $overdue = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '8',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
            'promised_at' => now()->subDay(),
        ]);

        $notOverdue = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '9',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
            'promised_at' => now()->addDay(),
        ]);

        expect($overdue->isOverdue())->toBeTrue();
        expect($notOverdue->isOverdue())->toBeFalse();
    });

    it('can fulfill', function (): void {
        $canFulfill = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '10',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $cannotFulfill = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '11',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 10,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        expect($canFulfill->canFulfill())->toBeTrue();
        expect($cannotFulfill->canFulfill())->toBeFalse();
    });

    it('can cancel', function (): void {
        $canCancel = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '12',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $cannotCancel = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '13',
            'status' => Cancelled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 10,
            'requested_at' => now(),
        ]);

        expect($canCancel->canCancel())->toBeTrue();
        expect($cannotCancel->canCancel())->toBeFalse();
    });

    it('fulfill partial', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '14',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->fulfill(5);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_fulfilled)->toBe(5);
        expect($backorder->fresh()->status)->toBeInstanceOf(PartiallyFulfilled::class);
    });

    it('fulfill complete', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '15',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->fulfill(10);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_fulfilled)->toBe(10);
        expect($backorder->fresh()->status)->toBeInstanceOf(Fulfilled::class);
        expect($backorder->fresh()->fulfilled_at)->not->toBeNull();
    });

    it('fulfill returns false when cannot fulfill', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '16',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 10,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->fulfill(5);

        expect($result)->toBeFalse();
    });

    it('cancel partial', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '17',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->cancel(5, 'Customer request');

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_cancelled)->toBe(5);
        expect($backorder->fresh()->metadata['cancellation_reason'])->toBe('Customer request');
    });

    it('cancel full', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '18',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->cancel(10);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_cancelled)->toBe(10);
        expect($backorder->fresh()->status)->toBeInstanceOf(Cancelled::class);
        expect($backorder->fresh()->cancelled_at)->not->toBeNull();
    });

    it('cancel returns false when cannot cancel', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '19',
            'status' => Cancelled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 10,
            'requested_at' => now(),
        ]);

        $result = $backorder->cancel(5);

        expect($result)->toBeFalse();
    });

    it('expire', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '20',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->expire();

        expect($result)->toBeTrue();
        expect($backorder->fresh()->status)->toBeInstanceOf(Expired::class);
        expect($backorder->fresh()->cancelled_at)->not->toBeNull();
    });

    it('expire returns false when not open', function (): void {
        $backorder = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '21',
            'status' => Fulfilled::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 10,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $backorder->expire();

        expect($result)->toBeFalse();
    });

    it('escalate', function (): void {
        $lowPriority = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '22',
            'status' => Pending::class,
            'priority' => BackorderPriority::Low,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $result = $lowPriority->escalate();

        expect($result)->toBeTrue();
        expect($lowPriority->fresh()->priority)->toBe(BackorderPriority::Normal);
    });

    it('escalate from normal to high', function (): void {
        $normalPriority = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '23',
            'status' => Pending::class,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $normalPriority->escalate();

        expect($normalPriority->fresh()->priority)->toBe(BackorderPriority::High);
    });

    it('escalate from high to urgent', function (): void {
        $highPriority = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '24',
            'status' => Pending::class,
            'priority' => BackorderPriority::High,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $highPriority->escalate();

        expect($highPriority->fresh()->priority)->toBe(BackorderPriority::Urgent);
    });

    it('escalate stays at urgent', function (): void {
        $urgentPriority = InventoryBackorder::create([
            'inventoryable_type' => 'Test',
            'inventoryable_id' => '25',
            'status' => Pending::class,
            'priority' => BackorderPriority::Urgent,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'requested_at' => now(),
        ]);

        $urgentPriority->escalate();

        expect($urgentPriority->fresh()->priority)->toBe(BackorderPriority::Urgent);
    });
});
