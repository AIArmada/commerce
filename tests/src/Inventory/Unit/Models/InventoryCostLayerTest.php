<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLocation;
use Carbon\CarbonImmutable;

describe('InventoryCostLayer', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    });

    it('can create cost layer', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1500,
            'total_cost_minor' => 150000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer)->toBeInstanceOf(InventoryCostLayer::class);
        expect($layer->quantity)->toBe(100);
        expect($layer->remaining_quantity)->toBe(100);
        expect($layer->unit_cost_minor)->toBe(1500);
    });

    it('inventoryable relationship', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->inventoryable)->not->toBeNull();
        expect($layer->inventoryable->id)->toBe($this->item->id);
    });

    it('location relationship', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->location)->not->toBeNull();
        expect($layer->location->id)->toBe($this->location->id);
    });

    it('batch relationship', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_id' => $batch->id,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->batch)->not->toBeNull();
        expect($layer->batch->id)->toBe($batch->id);
    });

    it('scope with remaining quantity', function (): void {
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 0,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        $layers = InventoryCostLayer::withRemainingQuantity()->get();

        expect($layers)->toHaveCount(1);
    });

    it('scope for model', function (): void {
        $item2 = InventoryItem::create(['name' => 'Item 2']);

        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);
        InventoryCostLayer::create([
            'inventoryable_type' => $item2->getMorphClass(),
            'inventoryable_id' => $item2->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 2000,
            'total_cost_minor' => 200000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        $layers = InventoryCostLayer::forModel($this->item)->get();

        expect($layers)->toHaveCount(1);
    });

    it('scope fifo order', function (): void {
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now()->subDays(5),
        ]);
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 2000,
            'total_cost_minor' => 200000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        $layers = InventoryCostLayer::fifoOrder()->get();

        expect($layers->first()->quantity)->toBe(50);
    });

    it('scope lifo order', function (): void {
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Lifo,
            'layer_date' => now()->subDays(5),
        ]);
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 2000,
            'total_cost_minor' => 200000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Lifo,
            'layer_date' => now(),
        ]);

        $layers = InventoryCostLayer::lifoOrder()->get();

        expect($layers->first()->quantity)->toBe(100);
    });

    it('scope using method', function (): void {
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 50000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);
        InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 2000,
            'total_cost_minor' => 200000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Lifo,
            'layer_date' => now(),
        ]);

        $fifoLayers = InventoryCostLayer::usingMethod(CostingMethod::Fifo)->get();
        $lifoLayers = InventoryCostLayer::usingMethod(CostingMethod::Lifo)->get();

        expect($fifoLayers)->toHaveCount(1);
        expect($lifoLayers)->toHaveCount(1);
    });

    it('has remaining quantity', function (): void {
        $withRemaining = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);
        $withoutRemaining = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 0,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($withRemaining->hasRemainingQuantity())->toBeTrue();
        expect($withoutRemaining->hasRemainingQuantity())->toBeFalse();
    });

    it('is fully consumed', function (): void {
        $consumed = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 0,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);
        $partial = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($consumed->isFullyConsumed())->toBeTrue();
        expect($partial->isFullyConsumed())->toBeFalse();
    });

    it('consumed quantity', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->consumedQuantity())->toBe(70);
    });

    it('remaining value', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->remainingValue())->toBe(30000);
    });

    it('consumed value', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->consumedValue())->toBe(70000);
    });

    it('consume reduces remaining quantity', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        $consumed = $layer->consume(30);

        expect($consumed)->toBe(30);
        expect($layer->fresh()->remaining_quantity)->toBe(70);
    });

    it('consume returns actual consumed when less available', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 20,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        $consumed = $layer->consume(50);

        expect($consumed)->toBe(20);
        expect($layer->fresh()->remaining_quantity)->toBe(0);
    });

    it('casts are correct', function (): void {
        $layer = InventoryCostLayer::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
            'total_cost_minor' => 100000,
            'currency' => 'USD',
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => now(),
        ]);

        expect($layer->quantity)->toBeInt();
        expect($layer->remaining_quantity)->toBeInt();
        expect($layer->costing_method)->toBe(CostingMethod::Fifo);
        expect($layer->layer_date)->toBeInstanceOf(CarbonImmutable::class);
    });
});
