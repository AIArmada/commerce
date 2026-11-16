<?php

declare(strict_types=1);

use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Support\PackageUnit;
use AIArmada\Shipping\Integrations\OrderShipmentUnits;
use AIArmada\Shipping\Models\ShipmentItem;
use Illuminate\Support\Collection;

it('only demands shippable components when verifying a mixed package shipment', function (): void {
    $component = static function (string $id, bool $shipping): array {
        return [
            'product_id' => $id,
            'variant_id' => null,
            'inventoryable_type' => 'catalog-item',
            'inventoryable_id' => $id,
            'quantity' => 2,
            'sku' => 'SKU-' . $id,
            'name' => 'Item ' . $id,
            'weight' => $shipping ? 100 : null,
            'dimensions' => null,
            'requires_shipping' => $shipping,
            'tracks_inventory' => $shipping,
        ];
    };

    $orderItem = new OrderItem;
    $orderItem->quantity = 1;
    $orderItem->metadata = [
        'requires_shipping' => true,
        'components' => [
            $component('service', false),
            $component('goods', true),
        ],
    ];

    $unit = new PackageUnit($orderItem, 1);

    $shipmentItem = new ShipmentItem;
    $shipmentItem->package_component_index = 1;
    $shipmentItem->quantity = 2;
    $shipmentItem->shippable_item_id = 'goods';
    $shipmentItem->shippable_item_type = 'catalog-item';

    $result = OrderShipmentUnits::assertUnit($unit, new Collection([$shipmentItem]));

    expect($result)->toHaveCount(1)
        ->and($result->keys()->all())->toBe([1]);
});
