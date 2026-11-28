<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Features\Traceability\Actions\UpdateTraceabilityDetails;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Sold;

it('only updates batch details without overriding stock balances or status', function (): void {
    $batch = InventoryBatch::factory()->create([
        'quantity_on_hand' => 100,
        'quantity_reserved' => 20,
        'status' => BatchStatus::Recalled->value,
        'metadata' => ['source' => 'receipt'],
    ]);

    $record = app(UpdateTraceabilityDetails::class)->batch($batch, [
        'lot_number' => 'CORRECTED',
        'quantity_on_hand' => 1000,
        'quantity_reserved' => 0,
        'status' => BatchStatus::Active->value,
        'metadata' => ['notes' => 'Corrected lot number'],
    ]);

    expect($record->lot_number)->toBe('CORRECTED')
        ->and($record->quantity_on_hand)->toBe(100)
        ->and($record->quantity_reserved)->toBe(20)
        ->and($record->status)->toBe(BatchStatus::Recalled->value)
        ->and($record->metadata)->toBe(['source' => 'receipt', 'notes' => 'Corrected lot number']);
});

it('does not bypass serial lifecycle through a details update', function (): void {
    $serial = InventorySerial::factory()->sold()->create();

    $record = app(UpdateTraceabilityDetails::class)->serial($serial, [
        'serial_number' => 'CORRECTED-SERIAL',
        'status' => SerialStatus::normalize(Available::class),
        'order_id' => null,
        'location_id' => null,
    ]);

    expect($record->serial_number)->toBe('CORRECTED-SERIAL')
        ->and($record->status)->toBeInstanceOf(Sold::class)
        ->and($record->order_id)->toBe($serial->order_id)
        ->and($record->location_id)->toBe($serial->location_id);
});

it('rejects negative traceability unit costs', function (): void {
    $serial = InventorySerial::factory()->create();
    $batch = InventoryBatch::factory()->create();
    $action = app(UpdateTraceabilityDetails::class);

    expect(fn () => $action->serial($serial, ['unit_cost_minor' => -1]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->batch($batch, ['unit_cost_minor' => -1]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects fractional costs and blank traceability identifiers without changing stored records', function (): void {
    $batch = InventoryBatch::factory()->create(['unit_cost_minor' => 100]);
    $serial = InventorySerial::factory()->create(['unit_cost_minor' => 200]);
    $action = app(UpdateTraceabilityDetails::class);

    foreach (['12.5', 'invalid', -1] as $cost) {
        expect(fn () => $action->batch($batch, ['unit_cost_minor' => $cost]))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $action->serial($serial, ['unit_cost_minor' => $cost]))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => $action->batch($batch, ['batch_number' => '   ']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $action->serial($serial, ['serial_number' => '   ']))->toThrow(InvalidArgumentException::class)
        ->and($batch->fresh()->unit_cost_minor)->toBe(100)
        ->and($serial->fresh()->unit_cost_minor)->toBe(200);

    expect($action->batch($batch, ['batch_number' => '  CLEAN-BATCH  ', 'unit_cost_minor' => '120'])->batch_number)->toBe('CLEAN-BATCH')
        ->and($action->serial($serial, ['serial_number' => '  CLEAN-SERIAL  ', 'unit_cost_minor' => '220'])->serial_number)->toBe('CLEAN-SERIAL');
});

it('rejects expiry before manufacture when either date is edited and rolls back other changes', function (): void {
    $batch = InventoryBatch::factory()->create([
        'manufactured_at' => '2025-01-01 00:00:00',
        'expires_at' => '2027-01-01 00:00:00',
        'lot_number' => 'ORIGINAL',
    ]);
    $action = app(UpdateTraceabilityDetails::class);

    expect(fn () => $action->batch($batch, [
        'expires_at' => '2024-01-01 00:00:00',
        'lot_number' => 'INVALID',
    ]))->toThrow(InvalidArgumentException::class, 'Batch expiry cannot precede manufacture');

    expect(fn () => $action->batch($batch, [
        'manufactured_at' => '2028-01-01 00:00:00',
    ]))->toThrow(InvalidArgumentException::class, 'Batch expiry cannot precede manufacture');

    $batch->refresh();

    expect($batch->lot_number)->toBe('ORIGINAL')
        ->and($batch->manufactured_at?->toDateString())->toBe('2025-01-01')
        ->and($batch->expires_at?->toDateString())->toBe('2027-01-01');

    expect($action->batch($batch, ['expires_at' => null])->expires_at)->toBeNull();
});
