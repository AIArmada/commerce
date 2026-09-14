<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Actions;

use AIArmada\Shipping\Actions\CreateShipment;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Events\ShipmentCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function committedShipmentPayload(string $reference): ShipmentData
{
    $address = new AddressData(
        name: 'Commit Tester',
        phone: '+60123456789',
        line1: '1 Commit Road',
        postcode: '50000',
        country: 'MY',
    );

    return ShipmentData::from([
        'reference' => $reference,
        'carrierCode' => 'manual',
        'serviceCode' => 'standard',
        'origin' => $address,
        'destination' => $address,
    ]);
}

describe('Shipment creation event timing', function (): void {
    it('dispatches the created event when the transaction commits', function (): void {
        Event::fake([ShipmentCreated::class]);

        CreateShipment::run(committedShipmentPayload('SHP-COMMIT'));

        Event::assertDispatched(ShipmentCreated::class, 1);
    });

    it('does not dispatch the created event when the transaction rolls back', function (): void {
        Event::fake([ShipmentCreated::class]);

        DB::beginTransaction();

        try {
            CreateShipment::run(committedShipmentPayload('SHP-ROLLBACK'));
        } finally {
            DB::rollBack();
        }

        Event::assertNotDispatched(ShipmentCreated::class);
    });
});
