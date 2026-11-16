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
        ->and($result->keys()->all())->toBe([1])
        ->and(PackageUnit::hasShippableComponents($orderItem))->toBeTrue();

    $orderItem->metadata = [
        'requires_shipping' => false,
        'components' => [$component('service', false)],
    ];

    expect(PackageUnit::hasShippableComponents($orderItem))->toBeFalse()
        ->and(PackageUnit::componentsOf($orderItem))->toHaveCount(1);
});

it('verifies exactly the shippable leaf indices of a deeply nested composition', function (): void {
    $component = static function (string $id, int $quantity, bool $shipping): array {
        return [
            'product_id' => $id,
            'variant_id' => null,
            'inventoryable_type' => 'product',
            'inventoryable_id' => $id,
            'quantity' => $quantity,
            'sku' => $id,
            'name' => $id,
            'weight' => $shipping ? 250 : null,
            'dimensions' => null,
            'requires_shipping' => $shipping,
            'tracks_inventory' => $shipping,
        ];
    };

    $items = [
        $component('nested-service', 2, false),
        [
            ...$component('nested-good', 6, true),
            'composition_path' => [
                ['product_id' => 'child-package', 'component_id' => 'child-edge', 'quantity' => 2],
                ['product_id' => 'nested-good', 'component_id' => 'leaf-edge', 'quantity' => 3],
            ],
        ],
        $component('other-service', 1, false),
    ];

    $orderItem = new OrderItem;
    $orderItem->quantity = 1;
    $orderItem->metadata = ['requires_shipping' => true, 'components' => $items];

    $unit = new PackageUnit($orderItem, 1);
    $line = new ShipmentItem;
    $line->package_component_index = 1;
    $line->quantity = 6;
    $line->shippable_item_id = 'nested-good';
    $line->shippable_item_type = 'product';

    expect(OrderShipmentUnits::assertUnit($unit, new Collection([$line])))->toHaveCount(1)
        ->and(PackageUnit::hasShippableComponents($orderItem))->toBeTrue();

    $line->quantity = 3;

    expect(fn () => OrderShipmentUnits::assertUnit($unit, new Collection([$line])))
        ->toThrow(\DomainException::class);
});
