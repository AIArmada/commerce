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
        $line = new OrderItem(['quantity' => $quantity]);
        $line->setRelation('purchasable', $item);
        $lines->push($line);
    }

    $order->setRelation('items', $lines);
    $service = app(FulfillmentLocationService::class);

    expect($service->getBestFulfillmentLocation($order)?->id)->toBe($full->id)
        ->and($service->canFulfillFromSingleLocation($order))->toBeTrue()
        ->and($service->getAvailabilitySummary($order)['total_required'])->toBe(6);

    $plan = $service->getFulfillmentPlan($order);

    expect($plan->sum(fn (array $location): int => array_sum(array_column($location['items'], 'quantity'))))->toBe(6);
});
