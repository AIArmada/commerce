<?php

declare(strict_types=1);

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentLabel;

describe('ShipmentLabel Model', function (): void {
    it('can create a shipment label with required fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $label = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'pdf',
            'generated_at' => now(),
        ]);

        expect($label)->toBeInstanceOf(ShipmentLabel::class);
        expect($label->shipment_id)->toBe($shipment->id);
        expect($label->format)->toBe('pdf');
        expect($label->generated_at)->not->toBeNull();
    });

    it('belongs to a shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $label = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'png',
            'generated_at' => now(),
        ]);

        expect($label->shipment)->toBeInstanceOf(Shipment::class);
        expect($label->shipment->id)->toBe($shipment->id);
    });

    it('can create labels with optional fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $label = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'zpl',
            'size' => '4x6',
            'url' => 'https://example.com/label.pdf',
            'content' => 'ZPL content here',
            'generated_at' => now(),
        ]);

        expect($label->size)->toBe('4x6');
        expect($label->url)->toBe('https://example.com/label.pdf');
        expect($label->content)->toBe('ZPL content here');
    });

    it('cascades delete when shipment is deleted', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'pdf',
            'generated_at' => now(),
        ]);

        ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'png',
            'generated_at' => now(),
        ]);

        expect(ShipmentLabel::count())->toBe(2);

        $shipment->delete();

        expect(ShipmentLabel::count())->toBe(0);
    });

    it('can check if label has url', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $labelWithUrl = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'pdf',
            'url' => 'https://example.com/label.pdf',
            'generated_at' => now(),
        ]);

        $labelWithoutUrl = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'zpl',
            'content' => 'ZPL content',
            'generated_at' => now(),
        ]);

        expect($labelWithUrl->hasUrl())->toBeTrue();
        expect($labelWithoutUrl->hasUrl())->toBeFalse();
    });

    it('can check if label has content', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $labelWithContent = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'zpl',
            'content' => 'ZPL content',
            'generated_at' => now(),
        ]);

        $labelWithoutContent = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'pdf',
            'url' => 'https://example.com/label.pdf',
            'generated_at' => now(),
        ]);

        expect($labelWithContent->hasContent())->toBeTrue();
        expect($labelWithoutContent->hasContent())->toBeFalse();
    });

    it('can decode base64 content', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $originalContent = 'This is the decoded content';
        $encodedContent = base64_encode($originalContent);

        $label = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'zpl',
            'content' => $encodedContent,
            'generated_at' => now(),
        ]);

        expect($label->getDecodedContent())->toBe($originalContent);
    });

    it('returns null when decoding content without content', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $label = ShipmentLabel::create([
            'shipment_id' => $shipment->id,
            'format' => 'pdf',
            'url' => 'https://example.com/label.pdf',
            'generated_at' => now(),
        ]);

        expect($label->getDecodedContent())->toBeNull();
    });
});