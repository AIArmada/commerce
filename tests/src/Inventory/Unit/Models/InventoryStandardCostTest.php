<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Valuation\Models\InventoryStandardCost;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostService;
use Carbon\CarbonImmutable;

describe('InventoryStandardCost', function (): void {
    beforeEach(function (): void {
        $this->item = InventoryItem::create(['name' => 'Test Item']);
    });

    it('create', function (): void {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 1000,
            'effective_from' => CarbonImmutable::now(),
        ]);

        expect($cost)->toBeInstanceOf(InventoryStandardCost::class);
        expect($cost->standard_cost_minor)->toBe(1000);
    });

    it('inventoryable relationship', function (): void {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($cost->inventoryable)->toBeInstanceOf(InventoryItem::class);
        expect($cost->inventoryable->id)->toBe($this->item->id);
    });

    it('scope for model', function (): void {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);
        InventoryStandardCost::factory()->create(); // Other item

        $costs = InventoryStandardCost::forModel($this->item)->get();

        expect($costs)->toHaveCount(1);
    });

    it('scope current', function (): void {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonth(),
            'effective_to' => null,
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonths(2),
            'effective_to' => CarbonImmutable::now()->subMonth(),
        ]);

        $current = InventoryStandardCost::forModel($this->item)->current()->get();

        expect($current)->toHaveCount(1);
    });

    it('scope effective at', function (): void {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 500,
            'effective_from' => CarbonImmutable::now()->subMonths(3),
            'effective_to' => CarbonImmutable::now()->subMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 600,
            'effective_from' => CarbonImmutable::now()->subMonth(),
        ]);

        $cost = InventoryStandardCost::forModel($this->item)
            ->effectiveAt(CarbonImmutable::now()->subMonths(2))
            ->first();

        expect($cost->standard_cost_minor)->toBe(500);
    });

    it('scope future', function (): void {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->addMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonth(),
        ]);

        $future = InventoryStandardCost::forModel($this->item)->future()->get();

        expect($future)->toHaveCount(1);
    });

    it('scope expired', function (): void {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonths(2),
            'effective_to' => CarbonImmutable::now()->subMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonth(),
            'effective_to' => null,
        ]);

        $expired = InventoryStandardCost::forModel($this->item)->expired()->get();

        expect($expired)->toHaveCount(1);
    });

    it('is current', function (): void {
        $current = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->subMonth(),
            'effective_to' => null,
        ]);

        $expired = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->subMonths(2),
            'effective_to' => CarbonImmutable::now()->subMonth(),
        ]);

        expect($current->isCurrent())->toBeTrue();
        expect($expired->isCurrent())->toBeFalse();
    });

    it('is future', function (): void {
        $future = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->addMonth(),
        ]);

        $current = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->subMonth(),
        ]);

        expect($future->isFuture())->toBeTrue();
        expect($current->isFuture())->toBeFalse();
    });

    it('is expired', function (): void {
        $expired = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->subMonths(2),
            'effective_to' => CarbonImmutable::now()->subMonth(),
        ]);

        $current = InventoryStandardCost::factory()->create([
            'effective_from' => CarbonImmutable::now()->subMonth(),
            'effective_to' => null,
        ]);

        expect($expired->isExpired())->toBeTrue();
        expect($current->isExpired())->toBeFalse();
    });

    it('expire', function (): void {
        config()->set('inventory.defaults.currency', 'MYR');
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subMonth(),
            'effective_to' => null,
        ]);

        $result = app(StandardCostService::class)->expireCurrentCost($this->item);

        expect($result)->toBeTrue();
        expect($cost->fresh()->effective_to)->not->toBeNull();
        expect($cost->fresh()->isExpired())->toBeTrue();
    });

    it('casts', function (): void {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 1000,
            'effective_from' => CarbonImmutable::now(),
            'metadata' => ['key' => 'value'],
        ]);

        $cost = $cost->fresh();

        expect($cost->standard_cost_minor)->toBeInt();
        expect($cost->effective_from)->toBeInstanceOf(CarbonImmutable::class);
        expect($cost->metadata)->toBeArray();
    });
});
