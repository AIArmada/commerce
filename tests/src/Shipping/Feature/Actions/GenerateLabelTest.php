<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\GenerateLabel;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentLabel;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\Draft;
use AIArmada\Shipping\States\Shipped;

describe('GenerateLabel Action', function (): void {
    it('generates a label for a shipped shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-LABEL-GEN',
            'carrier_code' => 'null',
            'status' => Shipped::class,
            'tracking_number' => 'TRACK-LABEL-001',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $labelData = new LabelData(
            format: 'pdf',
            url: 'https://example.com/label.pdf',
            content: base64_encode('pdf content'),
            size: 'a4',
            trackingNumber: 'TRACK-LABEL-001',
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('generateLabel')->with('TRACK-LABEL-001', [])->andReturn($labelData);

        $manager = Mockery::mock(ShippingManager::class);
        $manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $action = new GenerateLabel($manager);
        $label = $action->handle($shipment);

        expect($label)->toBeInstanceOf(ShipmentLabel::class);
        expect($label->format)->toBe('pdf');
        expect($label->url)->toBe('https://example.com/label.pdf');
        expect($label->size)->toBe('a4');
        expect($shipment->fresh()->label_url)->toBe('https://example.com/label.pdf');
        expect($shipment->fresh()->label_format)->toBe('pdf');
    });

    it('throws when shipment has no tracking number', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-LABEL-NO-TRK',
            'carrier_code' => 'null',
            'status' => Draft::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => GenerateLabel::run($shipment))
            ->toThrow(RuntimeException::class, 'Cannot generate label for shipment without tracking number');
    });
});
