<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Models;

use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;
use AIArmada\Shipping\Features\Shipments\Models\ShipmentOperation;
use AIArmada\Shipping\Features\Shipments\Persistence\ShipmentOperationWriter;
use InvalidArgumentException;
use RuntimeException;

function operationTestShipment(): Shipment
{
    return Shipment::query()->create([
        'reference' => 'SHP-OPERATION',
        'carrier_code' => 'manual',
        'origin_address' => ['name' => 'Origin'],
        'destination_address' => ['name' => 'Dest'],
    ]);
}

describe('Shipment operation start records', function (): void {
    it('returns the existing pending row instead of duplicating it', function (): void {
        $shipment = operationTestShipment();

        $first = app(ShipmentOperationWriter::class)->start($shipment, 'ship');
        $second = app(ShipmentOperationWriter::class)->start($shipment, 'ship');

        expect($second->getKey())->toBe($first->getKey())
            ->and(ShipmentOperation::query()->where('shipment_id', $shipment->getKey())->count())->toBe(1);
    });

    it('allows a fresh pending row after a definite failure', function (): void {
        $shipment = operationTestShipment();

        $first = app(ShipmentOperationWriter::class)->start($shipment, 'ship');
        app(ShipmentOperationWriter::class)->complete($first, CarrierOperationResult::failed('Rejected'));

        $second = app(ShipmentOperationWriter::class)->start($shipment, 'ship');

        expect($second->getKey())->not->toBe($first->getKey())
            ->and($second->status())->toBe(ShipmentOperationStatus::Pending);
    });

    it('never starts another carrier operation after success or an unknown result', function (CarrierOperationResult $result): void {
        $shipment = operationTestShipment();
        $writer = app(ShipmentOperationWriter::class);
        $first = $writer->start($shipment, 'create');
        $writer->complete($first, $result);

        $second = $writer->start($shipment, 'create');

        expect($second->getKey())->toBe($first->getKey())
            ->and($second->wasRecentlyCreated)->toBeFalse()
            ->and($shipment->operations()->count())->toBe(1);

        expect(fn () => $writer->start($shipment, 'cancel'))->toThrow(RuntimeException::class);
    })->with([
        'success' => [CarrierOperationResult::succeeded()],
        'unknown' => [CarrierOperationResult::unknown()],
    ]);

    it('rejects unpersisted shipments', function (): void {
        expect(fn () => app(ShipmentOperationWriter::class)->start(new Shipment, 'ship'))
            ->toThrow(InvalidArgumentException::class);
    });
});
