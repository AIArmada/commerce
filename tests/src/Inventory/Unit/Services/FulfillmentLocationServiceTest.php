<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Queries\FulfillmentLocationService;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

it('aggregates duplicate order item demand and prefers a fully stocked location', function (): void {
    $item = InventoryItem::create(['name' => 'Fulfillment SKU']);
    $partial = InventoryLocation::factory()->create(['priority' => 100, 'is_active' => true]);
    $full = InventoryLocation::factory()->create(['priority' => 1, 'is_active' => true]);

    foreach ([[$partial, 4], [$full, 8]] as [$location, $quantity]) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
        ]);
    }

    $order = new Order;
    $lines = new Collection;

    foreach ([3, 3] as $quantity) {
        $line = new OrderItem(['name' => 'Fulfillment SKU', 'quantity' => $quantity, 'metadata' => null]);
        $line->setRelation('purchasable', $item);
        $lines->push($line);
    }

    $order->setRelation('items', $lines);
    $service = app(FulfillmentLocationService::class);

    expect($service->getBestFulfillmentLocation($order)?->id)->toBe($full->id)
        ->and($service->canFulfillFromSingleLocation($order))->toBeTrue()
        ->and($service->getAvailabilitySummary($order)['total_required'])->toBe(6);

    $plan = $service->getFulfillmentPlan($order);

    expect($plan)->toHaveCount(1)
        ->and($plan->first()['location']->id)->toBe($full->id)
        ->and($plan->sum(fn (array $location): int => array_sum(array_column($location['items'], 'quantity'))))->toBe(6);
});

it('excludes inactive locations from the order availability summary and plan', function (): void {
    $item = InventoryItem::create(['name' => 'Active fulfillment SKU']);
    $active = InventoryLocation::factory()->create(['is_active' => true]);
    $inactive = InventoryLocation::factory()->create(['is_active' => false]);

    foreach ([[$active, 3, 1], [$inactive, 100, 0]] as [$location, $quantity, $reserved]) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => $reserved,
        ]);
    }

    $order = new Order;
    $line = new OrderItem(['name' => 'Active fulfillment SKU', 'quantity' => 5, 'metadata' => null]);
    $line->setRelation('purchasable', $item);
    $order->setRelation('items', new Collection([$line]));

    $service = app(FulfillmentLocationService::class);
    $summary = $service->getAvailabilitySummary($order);
    $plan = $service->getFulfillmentPlan($order);

    expect($summary['fully_available'])->toBeFalse()
        ->and($summary['total_available'])->toBe(2)
        ->and($summary['total_required'])->toBe(5)
        ->and($summary['by_item'][$item->getMorphClass() . ':' . $item->getKey()]['shortage'])->toBe(3)
        ->and($service->canFulfillFromSingleLocation($order))->toBeFalse()
        ->and($plan)->toHaveCount(1)
        ->and($plan->first()['location']->id)->toBe($active->id)
        ->and($plan->first()['items'][0]['quantity'])->toBe(2);
});

it('uses frozen composite component demand and combines standalone quantities', function (): void {
    $item = InventoryItem::create(['name' => 'Component']);
    $warehouse = InventoryLocation::factory()->create(['is_active' => true]);
    InventoryLevel::factory()->create([
        'inventoryable_type' => $item->getMorphClass(),
        'inventoryable_id' => $item->getKey(),
        'location_id' => $warehouse->id,
        'quantity_on_hand' => 6,
        'quantity_reserved' => 0,
    ]);

    $component = [
        'product_id' => null,
        'buyable_type' => $item->getMorphClass(),
        'buyable_id' => (string) $item->getKey(),
        'variant_id' => null,
        'inventoryable_type' => $item->getMorphClass(),
        'inventoryable_id' => (string) $item->getKey(),
        'quantity' => 2,
        'sku' => 'COMPONENT',
        'name' => 'Component',
        'weight' => null,
        'dimensions' => null,
        'requires_shipping' => true,
        'tracks_inventory' => true,
    ];

    $package = new OrderItem([
        'name' => 'Package',
        'quantity' => 3,
        'metadata' => ['components' => [$component]],
    ]);
    $package->setRelation('purchasable', null);
    $standalone = new OrderItem(['name' => 'Component', 'quantity' => 1, 'metadata' => null]);
    $standalone->setRelation('purchasable', $item);
    $order = new Order;
    $order->setRelation('items', new Collection([$package, $standalone]));

    $service = app(FulfillmentLocationService::class);
    $summary = $service->getAvailabilitySummary($order);
    $key = $item->getMorphClass() . ':' . $item->getKey();

    expect($summary['total_required'])->toBe(7)
        ->and($summary['by_item'][$key]['shortage'])->toBe(1)
        ->and($summary['fully_available'])->toBeFalse()
        ->and($service->canFulfillFromSingleLocation($order))->toBeFalse()
        ->and($service->getFulfillmentPlan($order)->first()['items'][0]['quantity'])->toBe(6);
});
