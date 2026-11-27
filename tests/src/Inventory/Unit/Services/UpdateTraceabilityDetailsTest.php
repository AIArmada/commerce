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
