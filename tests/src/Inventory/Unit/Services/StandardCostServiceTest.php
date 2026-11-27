<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Valuation\Models\InventoryStandardCost;
use AIArmada\Inventory\Features\Valuation\Persistence\StandardCostService;
use Carbon\CarbonImmutable;

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
            CarbonImmutable::now(),
            null,
            'admin',
            'Initial cost'
        );

        expect($cost)->toBeInstanceOf(InventoryStandardCost::class);
        expect($cost->standard_cost_minor)->toBe(1000);
        expect($cost->approved_by)->toBe('admin');
    });

    it('set standard cost expires current', function (): void {
        $oldCost = $this->service->setStandardCost($this->item, 500, CarbonImmutable::now()->subMonth());
        $newCost = $this->service->setStandardCost($this->item, 600, CarbonImmutable::now());

        expect($oldCost->fresh()->effective_to)->not->toBeNull();
        expect($newCost->effective_to)->toBeNull();
    });

    it('get current standard cost', function (): void {
        $this->service->setStandardCost($this->item, 1000, CarbonImmutable::now());

        $current = $this->service->getCurrentStandardCost($this->item);

        expect($current)->not->toBeNull();
        expect($current->standard_cost_minor)->toBe(1000);
    });

    it('get standard cost at', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now()->subMonths(2), CarbonImmutable::now()->subMonth());
        $this->service->setStandardCost($this->item, 600, CarbonImmutable::now()->subMonth());

        $pastCost = $this->service->getStandardCostAt($this->item, CarbonImmutable::now()->subMonths(1)->subDays(15));

        expect($pastCost->standard_cost_minor)->toBe(500);
    });

    it('get current cost value', function (): void {
        $this->service->setStandardCost($this->item, 1000, CarbonImmutable::now());

        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBe(1000);
    });

    it('get current cost value returns null when none', function (): void {
        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBeNull();
    });

    it('calculate valuation', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now());

        $valuation = $this->service->calculateValuation($this->item, 100);

        expect($valuation['quantity'])->toBe(100);
        expect($valuation['value'])->toBe(50000);
        expect($valuation['unit_cost'])->toBe(500);
    });

    it('calculate variance favorable', function (): void {
        $this->service->setStandardCost($this->item, 1000, CarbonImmutable::now());

        // Actual cost is lower than standard = favorable
        $variance = $this->service->calculateVariance($this->item, 800);

        expect($variance['variance'])->toBe(-200);
        expect($variance['favorable'])->toBeTrue();
    });

    it('calculate variance unfavorable', function (): void {
        $this->service->setStandardCost($this->item, 1000, CarbonImmutable::now());

        // Actual cost is higher than standard = unfavorable
        $variance = $this->service->calculateVariance($this->item, 1200);

        expect($variance['variance'])->toBe(200);
        expect($variance['favorable'])->toBeFalse();
    });

    it('get cost history', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now()->subMonths(2), CarbonImmutable::now()->subMonth());
        $this->service->setStandardCost($this->item, 600, CarbonImmutable::now()->subMonth(), CarbonImmutable::now());
        $this->service->setStandardCost($this->item, 700, CarbonImmutable::now());

        $history = $this->service->getCostHistory($this->item);

        expect($history)->toHaveCount(3);
    });

    it('get future costs', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now());
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 600,
            'effective_from' => CarbonImmutable::now()->addMonth(),
        ]);

        $future = $this->service->getFutureCosts($this->item);

        expect($future)->toHaveCount(1);
        expect($future->first()->standard_cost_minor)->toBe(600);
    });

    it('expire current cost', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now());

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

        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now());

        expect($this->service->hasStandardCost($this->item))->toBeTrue();
    });

    it('schedule cost change', function (): void {
        $this->service->setStandardCost($this->item, 500, CarbonImmutable::now());

        $scheduled = $this->service->scheduleCostChange(
            $this->item,
            600,
            CarbonImmutable::now()->addMonth(),
            'admin',
            'Annual review'
        );

        expect($scheduled)->toBeInstanceOf(InventoryStandardCost::class);
        expect($scheduled->standard_cost_minor)->toBe(600);
    });

    it('schedule cost change throws for past date', function (): void {
        $this->service->scheduleCostChange($this->item, 600, CarbonImmutable::now()->subDay());
    })->throws(InvalidArgumentException::class);

    it('cancel scheduled cost', function (): void {
        $scheduled = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->addMonth(),
        ]);

        $result = $this->service->cancelScheduledCost($scheduled);

        expect($result)->toBeTrue();
        expect(InventoryStandardCost::find($scheduled->id))->toBeNull();
    });

    it('cancel scheduled cost throws for active cost', function (): void {
        $activeCost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => CarbonImmutable::now()->subDay(),
            'effective_to' => null,
        ]);

        $this->service->cancelScheduledCost($activeCost);
    })->throws(InvalidArgumentException::class);
});

it('rejects negative standard costs and invalid effective periods', function (): void {
    $service = new StandardCostService;
    $item = InventoryItem::create(['name' => 'Validation Item']);
    $start = CarbonImmutable::now()->addDay();

    expect(fn () => $service->setStandardCost($item, -1, $start))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->setStandardCost($item, 100, $start, $start))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->scheduleCostChange($item, -1, $start))->toThrow(InvalidArgumentException::class);
});

it('keeps scheduled cost periods contiguous and restores the previous period when canceled', function (): void {
    $service = new StandardCostService;
    $item = InventoryItem::create(['name' => 'Scheduled Item']);
    $start = CarbonImmutable::now()->subDay()->startOfSecond();
    $firstDate = $start->addMonth();
    $secondDate = $firstDate->addMonth();

    $current = $service->setStandardCost($item, 100, $start);
    $second = $service->scheduleCostChange($item, 300, $secondDate);
    $first = $service->scheduleCostChange($item, 200, $firstDate);

    expect($current->refresh()->effective_to->equalTo($firstDate))->toBeTrue()
        ->and($first->effective_to->equalTo($secondDate))->toBeTrue()
        ->and($service->getCostValueAt($item, $firstDate->addDay()))->toBe(200);

    $service->cancelScheduledCost($first);

    expect($current->refresh()->effective_to->equalTo($secondDate))->toBeTrue()
        ->and($service->getCostValueAt($item, $firstDate->addDay()))->toBe(100)
        ->and($service->getCostValueAt($item, $secondDate->addDay()))->toBe(300);
});
