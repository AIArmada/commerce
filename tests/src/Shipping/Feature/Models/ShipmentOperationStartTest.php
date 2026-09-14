<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Models;

use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Enums\ShipmentOperationStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentOperation;
use InvalidArgumentException;

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

        $first = ShipmentOperation::recordStart($shipment, 'ship');
        $second = ShipmentOperation::recordStart($shipment, 'ship');

        expect($second->getKey())->toBe($first->getKey())
            ->and(ShipmentOperation::query()->where('shipment_id', $shipment->getKey())->count())->toBe(1);
    });

    it('allows a fresh pending row after the previous operation completed', function (): void {
        $shipment = operationTestShipment();

        $first = ShipmentOperation::recordStart($shipment, 'ship');
        $first->complete(CarrierOperationResult::succeeded());

        $second = ShipmentOperation::recordStart($shipment, 'ship');

        expect($second->getKey())->not->toBe($first->getKey())
            ->and($second->status())->toBe(ShipmentOperationStatus::Pending);
    });

    it('rejects unpersisted shipments', function (): void {
        expect(fn () => ShipmentOperation::recordStart(new Shipment, 'ship'))
            ->toThrow(InvalidArgumentException::class);
    });
});
