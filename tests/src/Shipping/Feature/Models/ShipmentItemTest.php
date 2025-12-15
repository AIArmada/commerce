<?php

declare(strict_types=1);

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentItem;

describe('ShipmentItem Model', function (): void {
    it('can create a shipment item with required fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Test Item',
            'quantity' => 2,
            'weight' => 500,
            'declared_value' => 1000,
        ]);

        expect($item)->toBeInstanceOf(ShipmentItem::class);
        expect($item->shipment_id)->toBe($shipment->id);
        expect($item->name)->toBe('Test Item');
        expect($item->quantity)->toBe(2);
        expect($item->weight)->toBe(500);
        expect($item->declared_value)->toBe(1000);
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

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Test Item',
            'quantity' => 1,
            'weight' => 100,
            'declared_value' => 500,
        ]);

        expect($item->shipment)->toBeInstanceOf(Shipment::class);
        expect($item->shipment->id)->toBe($shipment->id);
    });

    it('can create items with optional fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'sku' => 'TEST-SKU-123',
            'name' => 'Premium Item',
            'description' => 'A premium test item',
            'quantity' => 3,
            'weight' => 750,
            'declared_value' => 2500,
            'hs_code' => '1234.56.78',
            'origin_country' => 'US',
            'shippable_item_id' => 'item-123',
            'shippable_item_type' => 'Product',
        ]);

        expect($item->sku)->toBe('TEST-SKU-123');
        expect($item->description)->toBe('A premium test item');
        expect($item->hs_code)->toBe('1234.56.78');
        expect($item->origin_country)->toBe('US');
        expect($item->shippable_item_id)->toBe('item-123');
        expect($item->shippable_item_type)->toBe('Product');
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

        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Item 1',
            'quantity' => 1,
            'weight' => 100,
            'declared_value' => 500,
        ]);

        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Item 2',
            'quantity' => 2,
            'weight' => 200,
            'declared_value' => 1000,
        ]);

        expect(ShipmentItem::count())->toBe(2);

        $shipment->delete();

        expect(ShipmentItem::count())->toBe(0);
    });

    it('can calculate total weight', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Test Item',
            'quantity' => 3,
            'weight' => 500, // 500g each
            'declared_value' => 1000,
        ]);

        expect($item->getTotalWeight())->toBe(1500); // 3 * 500
    });

    it('can calculate total value', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'name' => 'Test Item',
            'quantity' => 2,
            'weight' => 500,
            'declared_value' => 750, // $7.50 each
        ]);

        expect($item->getTotalValue())->toBe(1500); // 2 * 750
    });

    it('can relate to shippable item polymorphically', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'shippable_item_id' => 'product-123',
            'shippable_item_type' => 'App\\Models\\Product',
            'name' => 'Test Item',
            'quantity' => 1,
            'weight' => 500,
            'declared_value' => 1000,
        ]);

        expect($item->shippable_item_id)->toBe('product-123');
        expect($item->shippable_item_type)->toBe('App\\Models\\Product');
        // Note: We can't test the actual relationship resolution without a real model
    });


});