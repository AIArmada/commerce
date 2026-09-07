<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryStandardCost;
use AIArmada\Inventory\Services\Costing\StandardCostService;

describe('StandardCostService', function (): void {
    beforeEach(function (): void {
        $this->service = new StandardCostService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    });

    it('set standard cost', function (): void {
        $cost = $this->service->setStandardCost(
            $this->item,
            1000,
            now(),
            null,
            'admin',
            'Initial cost'
        );

        expect($cost)->toBeInstanceOf(InventoryStandardCost::class);
        expect($cost->standard_cost_minor)->toBe(1000);
        expect($cost->approved_by)->toBe('admin');
    });

    it('set standard cost expires current', function (): void {
        $oldCost = $this->service->setStandardCost($this->item, 500, now()->subMonth());
        $newCost = $this->service->setStandardCost($this->item, 600, now());

        expect($oldCost->fresh()->effective_to)->not->toBeNull();
        expect($newCost->effective_to)->toBeNull();
    });

    it('get current standard cost', function (): void {
        $this->service->setStandardCost($this->item, 1000, now());

        $current = $this->service->getCurrentStandardCost($this->item);

        expect($current)->not->toBeNull();
        expect($current->standard_cost_minor)->toBe(1000);
    });

    it('get standard cost at', function (): void {
        $this->service->setStandardCost($this->item, 500, now()->subMonths(2), now()->subMonth());
        $this->service->setStandardCost($this->item, 600, now()->subMonth());

        $pastCost = $this->service->getStandardCostAt($this->item, now()->subMonths(1)->subDays(15));

        expect($pastCost->standard_cost_minor)->toBe(500);
    });

    it('get current cost value', function (): void {
        $this->service->setStandardCost($this->item, 1000, now());

        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBe(1000);
    });

    it('get current cost value returns null when none', function (): void {
        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBeNull();
    });

    it('calculate valuation', function (): void {
        $this->service->setStandardCost($this->item, 500, now());

        $valuation = $this->service->calculateValuation($this->item, 100);

        expect($valuation['quantity'])->toBe(100);
        expect($valuation['value'])->toBe(50000);
        expect($valuation['unit_cost'])->toBe(500);
    });

    it('calculate variance favorable', function (): void {
        $this->service->setStandardCost($this->item, 1000, now());

        // Actual cost is lower than standard = favorable
        $variance = $this->service->calculateVariance($this->item, 800);

        expect($variance['variance'])->toBe(-200);
        expect($variance['favorable'])->toBeTrue();
    });

    it('calculate variance unfavorable', function (): void {
        $this->service->setStandardCost($this->item, 1000, now());

        // Actual cost is higher than standard = unfavorable
        $variance = $this->service->calculateVariance($this->item, 1200);

        expect($variance['variance'])->toBe(200);
        expect($variance['favorable'])->toBeFalse();
    });

    it('get cost history', function (): void {
        $this->service->setStandardCost($this->item, 500, now()->subMonths(2), now()->subMonth());
        $this->service->setStandardCost($this->item, 600, now()->subMonth(), now());
        $this->service->setStandardCost($this->item, 700, now());

        $history = $this->service->getCostHistory($this->item);

        expect($history)->toHaveCount(3);
    });

    it('get future costs', function (): void {
        $this->service->setStandardCost($this->item, 500, now());
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 600,
            'effective_from' => now()->addMonth(),
        ]);

        $future = $this->service->getFutureCosts($this->item);

        expect($future)->toHaveCount(1);
        expect($future->first()->standard_cost_minor)->toBe(600);
    });

    it('expire current cost', function (): void {
        $this->service->setStandardCost($this->item, 500, now());

        $result = $this->service->expireCurrentCost($this->item);

        expect($result)->toBeTrue();

        $current = $this->service->getCurrentStandardCost($this->item);
        expect($current)->toBeNull();
    });

    it('expire current cost returns false when none', function (): void {
        $result = $this->service->expireCurrentCost($this->item);

        expect($result)->toBeFalse();
    });

    it('has standard cost', function (): void {
        expect($this->service->hasStandardCost($this->item))->toBeFalse();

        $this->service->setStandardCost($this->item, 500, now());

        expect($this->service->hasStandardCost($this->item))->toBeTrue();
    });

    it('schedule cost change', function (): void {
        $this->service->setStandardCost($this->item, 500, now());

        $scheduled = $this->service->scheduleCostChange(
            $this->item,
            600,
            now()->addMonth(),
            'admin',
            'Annual review'
        );

        expect($scheduled)->toBeInstanceOf(InventoryStandardCost::class);
        expect($scheduled->standard_cost_minor)->toBe(600);
    });

    it('schedule cost change throws for past date', function (): void {
        $this->service->scheduleCostChange($this->item, 600, now()->subDay());
    })->throws(InvalidArgumentException::class);

    it('cancel scheduled cost', function (): void {
        $scheduled = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->addMonth(),
        ]);

        $result = $this->service->cancelScheduledCost($scheduled);

        expect($result)->toBeTrue();
        expect(InventoryStandardCost::find($scheduled->id))->toBeNull();
    });

    it('cancel scheduled cost throws for active cost', function (): void {
        $activeCost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ]);

        $this->service->cancelScheduledCost($activeCost);
    })->throws(InvalidArgumentException::class);
});
