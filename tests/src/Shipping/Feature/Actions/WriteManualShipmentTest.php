<?php

declare(strict_types=1);

use AIArmada\Shipping\Features\Shipments\Api\ManageManualShipment;
use AIArmada\Shipping\Features\Shipments\Models\Shipment;

it('creates a pending shipment without accepting lifecycle or owner fields', function (): void {
    config(['shipping.features.owner.enabled' => false]);
    $api = app(ManageManualShipment::class);
    $id = $api->create([
        'reference' => 'MANUAL-TEST',
        'carrier_code' => 'manual',
        'origin_address' => [],
        'destination_address' => [],
        'status' => 'delivered',
        'tracking_number' => 'injected',
        'owner_id' => 'injected',
    ]);
    $shipment = Shipment::query()->findOrFail($id);

    expect($shipment->isPending())->toBeTrue()
        ->and($shipment->tracking_number)->toBeNull()
        ->and($shipment->owner_id)->toBeNull();

    expect(fn () => $api->update($id, ['total_weight' => -1]))->toThrow(DomainException::class);
    expect($api->delete($id))->toBeTrue();
});
