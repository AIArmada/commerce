<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Unit\Models;

use AIArmada\Shipping\Models\Shipment;

describe('Shipment owner mass assignment', function (): void {
    it('ignores owner attributes passed to mass assignment', function (): void {
        $shipment = Shipment::query()->create([
            'owner_type' => 'SpoofedOwner',
            'owner_id' => 'spoofed-1',
            'reference' => 'SHP-NO-SPOOF',
            'carrier_code' => 'manual',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($shipment->owner_type)->toBeNull()
            ->and($shipment->owner_id)->toBeNull();
    });
});
