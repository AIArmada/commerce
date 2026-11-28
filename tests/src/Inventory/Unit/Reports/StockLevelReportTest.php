<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Features\Replenishment\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Features\Reporting\Queries\StockLevelReport;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Valuation\Api\InventoryValues;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Features\Valuation\Models\InventoryStandardCost;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->report = new StockLevelReport;
});

describe('StockLevelReport', function (): void {
    describe('getStockByLocation', function (): void {
        it('returns empty collection when no levels', function (): void {
            $result = $this->report->getStockByLocation();

            expect($result)->toBeEmpty();
        });

        it('groups stock by location', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result)->toHaveCount(2);
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result->first())->toHaveKeys([
                'location_id',
                'location_name',
                'sku_count',
                'total_quantity',
                'total_value',
                'low_stock_count',
                'out_of_stock_count',
            ]);
        });

        it('calculates total quantity correctly', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result->first()['total_quantity'])->toBe(100);
        });
    });

    describe('getAbcAnalysis', function (): void {
        it('returns empty collection when no valued stock', function (): void {
            expect($this->report->getAbcAnalysis())->toBeEmpty();
        });

        it('ranks by monetary value and retains the full denominator when limited', function (): void {
            config(['inventory.defaults.currency' => 'MYR']);

            foreach ([[1, 850], [10, 10], [50, 1]] as [$quantity, $unitCost]) {
                $item = InventoryItem::create(['name' => 'Valued stock']);

                InventoryCostLayer::factory()->create([
                    'inventoryable_type' => $item->getMorphClass(),
                    'inventoryable_id' => $item->getKey(),
                    'location_id' => $this->location->id,
                    'quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'unit_cost_minor' => $unitCost,
                    'total_cost_minor' => $quantity * $unitCost,
                    'costing_method' => CostingMethod::Fifo,
                    'currency' => 'MYR',
                ]);
            }

            $result = $this->report->getAbcAnalysis();

            expect($result->pluck('total_value')->all())->toBe([850, 100, 50])
                ->and($result->pluck('classification')->all())->toBe(['A', 'B', 'C'])
                ->and($result->pluck('cumulative_percentage')->all())->toBe([85.0, 95.0, 100.0])
                ->and($this->report->getAbcAnalysis(1)->first()['cumulative_percentage'])->toBe(85.0);
        });

        it('excludes uncosted inventory and other costing methods and currencies', function (): void {
            config(['inventory.defaults.currency' => 'MYR']);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            foreach ([[CostingMethod::WeightedAverage, 'MYR'], [CostingMethod::Fifo, 'USD']] as [$method, $currency]) {
                InventoryCostLayer::factory()->create([
                    'inventoryable_type' => $this->item->getMorphClass(),
                    'inventoryable_id' => $this->item->getKey(),
                    'location_id' => $this->location->id,
                    'quantity' => 1,
                    'remaining_quantity' => 1,
                    'unit_cost_minor' => 500,
                    'total_cost_minor' => 500,
                    'costing_method' => $method,
                    'currency' => $currency,
                ]);
            }

            expect($this->report->getAbcAnalysis())->toBeEmpty()
                ->and($this->report->getAbcAnalysis(method: CostingMethod::WeightedAverage)->first())
                ->toMatchArray([
                    'inventoryable_type' => $this->item->getMorphClass(),
                    'inventoryable_id' => $this->item->getKey(),
                    'total_value' => 500,
                    'cumulative_percentage' => 100.0,
                    'classification' => 'A',
                ]);
        });
    });

    describe('getBatchAgingAnalysis', function (): void {
        it('returns all age ranges', function (): void {
            $result = $this->report->getBatchAgingAnalysis();

            expect($result)->toHaveCount(6);
        });

        it('returns correct data structure for each range', function (): void {
            $result = $this->report->getBatchAgingAnalysis();

            expect($result->first())->toHaveKeys([
                'age_range',
                'batch_count',
                'total_quantity',
                'total_value',
                'expiring_soon',
            ]);
        });

        it('groups batches by age', function (): void {
            InventoryBatch::factory()
                ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
                ->create([
                    'location_id' => $this->location->id,
                    'manufactured_at' => now()->subDays(15),
                    'quantity_on_hand' => 100,
                ]);

            $result = $this->report->getBatchAgingAnalysis();
            $first = $result->firstWhere('age_range', '0-30 days');

            expect($first['batch_count'])->toBe(1);
        });

        it('identifies expiring soon batches', function (): void {
            InventoryBatch::factory()
                ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
                ->create([
                    'location_id' => $this->location->id,
                    'manufactured_at' => now()->subDays(10),
                    'expires_at' => now()->addDays(15),
                    'quantity_on_hand' => 100,
                ]);

            $result = $this->report->getBatchAgingAnalysis();
            $first = $result->firstWhere('age_range', '0-30 days');

            expect($first['expiring_soon'])->toBe(1);
        });
    });

    describe('getReorderStatus', function (): void {
        it('returns complete status structure', function (): void {
            $result = $this->report->getReorderStatus();

            expect($result)->toHaveKeys([
                'items_below_reorder_point',
                'pending_suggestions',
                'approved_suggestions',
                'total_suggested_value',
                'urgent_reorders',
            ]);
        });

        it('counts items below reorder point', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'reorder_point' => 10,
                'alert_status' => AlertStatus::LowStock->value,
            ]);

            $result = $this->report->getReorderStatus();

            expect($result['items_below_reorder_point'])->toBe(1);
        });

        it('counts pending suggestions', function (): void {
            InventoryReorderSuggestion::factory()
                ->forModel($this->item)
                ->pending()
                ->create([
                    'location_id' => $this->location->id,
                ]);

            $result = $this->report->getReorderStatus();

            expect($result['pending_suggestions'])->toBe(1);
        });
    });

    describe('getStockDistribution', function (): void {
        it('returns empty when no multi-location items', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result)->toBeEmpty();
        });

        it('returns items with multiple locations', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result)->toHaveCount(1);
            expect($result->first()['location_count'])->toBe(2);
        });

        it('returns correct data structure', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'location_count',
                'total_quantity',
                'max_location_quantity',
                'min_location_quantity',
                'concentration_ratio',
            ]);
        });

        it('calculates concentration ratio', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result->first()['concentration_ratio'])->toBe(50.0);
        });
    });

    describe('getDeadStock', function (): void {
        it('returns empty when no stagnant stock', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now(),
            ]);

            $result = $this->report->getDeadStock();

            expect($result)->toBeEmpty();
        });

        it('returns stagnant items', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(120),
            ]);

            $result = $this->report->getDeadStock();

            expect($result)->toHaveCount(1);
        });

        it('respects days threshold', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(50),
            ]);

            $result = $this->report->getDeadStock(90);

            expect($result)->toBeEmpty();
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(120),
            ]);

            $result = $this->report->getDeadStock();

            expect($result->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'quantity',
                'value',
                'location_id',
                'days_stagnant',
            ]);
        });
    });

    describe('getCycleCountMetrics', function (): void {
        it('returns 100% accuracy when no counts', function (): void {
            $result = $this->report->getCycleCountMetrics();

            expect($result['accuracy_percentage'])->toBe(100.0);
        });

        it('returns complete metrics structure', function (): void {
            $result = $this->report->getCycleCountMetrics();

            expect($result)->toHaveKeys([
                'total_counts',
                'accurate_counts',
                'accuracy_percentage',
                'total_variance_units',
                'total_variance_value',
                'avg_variance_percentage',
            ]);
        });

        it('calculates accuracy from cycle counts', function (): void {
            // Accurate count
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 0,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $result = $this->report->getCycleCountMetrics();

            expect($result['total_counts'])->toBe(1);
            expect($result['accurate_counts'])->toBe(1);
            expect($result['accuracy_percentage'])->toBe(100.0);
        });

        it('calculates variance', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $result = $this->report->getCycleCountMetrics();

            expect($result['total_variance_units'])->toBe(5);
            expect($result['accuracy_percentage'])->toBe(0.0);
        });
    });
});

it('reports on-hand batch value and excludes expired batches from upcoming expiry', function (): void {
    $item = InventoryItem::create(['name' => 'Aging Item']);
    $location = InventoryLocation::factory()->create();

    foreach ([now()->subDay(), now()->addDays(20), now()->addDays(60)] as $expiry) {
        InventoryBatch::factory()
            ->forInventoryable($item->getMorphClass(), $item->getKey())
            ->create([
                'location_id' => $location->id,
                'manufactured_at' => now()->subDays(15),
                'expires_at' => $expiry,
                'quantity_on_hand' => 10,
                'unit_cost_minor' => 50,
            ]);
    }

    $bucket = app(StockLevelReport::class)->getBatchAgingAnalysis()->firstWhere('age_range', '0-30 days');

    expect($bucket['batch_count'])->toBe(3)
        ->and($bucket['total_quantity'])->toBe(30)
        ->and($bucket['total_value'])->toBe(1500)
        ->and($bucket['expiring_soon'])->toBe(1);
});

it('counts distinct reorder items and aggregates suggestion statuses', function (): void {
    $otherLocation = InventoryLocation::factory()->create();

    foreach ([$this->location, $otherLocation] as $location) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
            'alert_status' => AlertStatus::LowStock->value,
        ]);
    }

    InventoryReorderSuggestion::factory()->forModel($this->item)->pending()->critical()->create([
        'location_id' => $this->location->id,
    ]);
    InventoryReorderSuggestion::factory()->forModel($this->item)->approved()->critical()->create([
        'location_id' => $otherLocation->id,
    ]);

    $result = $this->report->getReorderStatus();

    expect($result['items_below_reorder_point'])->toBe(1)
        ->and($result['pending_suggestions'])->toBe(1)
        ->and($result['approved_suggestions'])->toBe(1)
        ->and($result['urgent_reorders'])->toBe(1);
});

it('reports real available stock alert counts by location', function (): void {
    foreach ([[10, 7, 5], [10, 10, 5], [20, 0, 5]] as [$onHand, $reserved, $reorderPoint]) {
        $item = InventoryItem::create(['name' => 'Stock item']);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'reorder_point' => $reorderPoint,
        ]);
    }

    $result = $this->report->getStockByLocation()->first();

    expect($result['sku_count'])->toBe(3)
        ->and($result['total_quantity'])->toBe(40)
        ->and($result['low_stock_count'])->toBe(1)
        ->and($result['out_of_stock_count'])->toBe(1);
});

it('reports remaining cost-layer value separately for each location', function (): void {
    config(['inventory.defaults.currency' => 'MYR']);
    $otherLocation = InventoryLocation::factory()->create();

    foreach ([[$this->location, 3, 100], [$otherLocation, 2, 250]] as [$location, $quantity, $unitCost]) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost_minor' => $unitCost,
            'total_cost_minor' => $quantity * $unitCost,
            'costing_method' => CostingMethod::Fifo,
            'currency' => 'MYR',
        ]);
    }

    $values = $this->report->getStockByLocation()->pluck('total_value', 'location_id');

    expect($values->get($this->location->id))->toBe(300)
        ->and($values->get($otherLocation->id))->toBe(500);
});

it('values actionable reorder suggestions using their linked supplier and reporting currency', function (): void {
    config(['inventory.defaults.currency' => 'MYR']);

    $supplier = InventorySupplierLeadtime::factory()->forModel($this->item)->create([
        'unit_cost_minor' => 100,
        'minimum_order_quantity' => 10,
        'order_multiple' => 5,
        'currency' => 'MYR',
    ]);

    InventoryReorderSuggestion::factory()->forModel($this->item)->pending()->create([
        'location_id' => $this->location->id,
        'supplier_leadtime_id' => $supplier->id,
        'suggested_quantity' => 12,
    ]);

    InventoryReorderSuggestion::factory()->forModel($this->item)->approved()->create([
        'location_id' => $this->location->id,
        'supplier_leadtime_id' => $supplier->id,
        'suggested_quantity' => 4,
    ]);

    InventoryReorderSuggestion::factory()->forModel($this->item)->pending()->create([
        'location_id' => $this->location->id,
        'supplier_leadtime_id' => null,
    ]);

    InventoryReorderSuggestion::factory()->forModel($this->item)->ordered()->create([
        'location_id' => $this->location->id,
        'supplier_leadtime_id' => $supplier->id,
        'suggested_quantity' => 100,
    ]);

    $report = $this->report->getReorderStatus();

    expect($report['total_suggested_value'])->toBe(2500)
        ->and($report['unpriced_suggestions'])->toBe(1);
});

it('reports dead-stock value only for its exact item and location', function (): void {
    config(['inventory.defaults.currency' => 'MYR']);
    $other = InventoryLocation::factory()->create();
    $uncosted = InventoryItem::create(['name' => 'Uncosted stock']);

    foreach ([$this->item, $uncosted] as $item) {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'updated_at' => now()->subDays(120),
        ]);
    }

    foreach ([[$this->location, 4], [$other, 9]] as [$location, $remaining]) {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity' => 10,
            'remaining_quantity' => $remaining,
            'unit_cost_minor' => 100,
            'total_cost_minor' => 1000,
            'costing_method' => CostingMethod::Fifo,
            'currency' => 'MYR',
        ]);
    }

    $report = $this->report->getDeadStock()->keyBy('inventoryable_id');

    expect($report->get($this->item->getKey())['value'])->toBe(400)
        ->and($report->get($uncosted->getKey())['value'])->toBeNull();
});

it('reports cycle count percentages from recorded before quantities without inventing historical costs', function (): void {
    foreach ([[10, 2], [20, 4], [10, 0]] as [$before, $variance]) {
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'to_location_id' => $this->location->id,
            'type' => MovementType::Adjustment,
            'quantity' => $variance,
            'quantity_before' => $before,
            'quantity_after' => $before + $variance,
            'reason' => 'cycle_count',
            'occurred_at' => now()->subDay(),
        ]);
    }

    $result = $this->report->getCycleCountMetrics();

    expect($result['total_counts'])->toBe(3)
        ->and($result['accurate_counts'])->toBe(1)
        ->and($result['total_variance_units'])->toBe(6)
        ->and($result['avg_variance_percentage'])->toBe(13.33)
        ->and($result['total_variance_value'])->toBeNull();
});

it('uses effective standard cost and physical on-hand balances across valuation reports', function (): void {
    config(['inventory.defaults.currency' => 'MYR']);

    InventoryLevel::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'quantity_on_hand' => 10,
        'quantity_reserved' => 2,
        'updated_at' => now()->subDays(120),
    ]);

    foreach ([
        ['MYR', 120, now()->subMonth(), null],
        ['USD', 900, now()->subDay(), null],
        ['MYR', 500, now()->addDay(), null],
        ['MYR', 60, now()->subMonths(3), now()->subMonths(2)],
    ] as [$currency, $cost, $from, $to]) {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => $cost,
            'currency' => $currency,
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }

    expect($this->report->getStockByLocation(CostingMethod::Standard)->first()['total_value'])->toBe(1200)
        ->and($this->report->getAbcAnalysis(method: CostingMethod::Standard)->first()['total_value'])->toBe(1200)
        ->and($this->report->getDeadStock(method: CostingMethod::Standard)->first()['value'])->toBe(1200)
        ->and(app(InventoryValues::class)->total(CostingMethod::Standard, 'MYR'))->toBe(1200);
});

it('hydrates low stock levels and orders their deficit for the dashboard', function (): void {
    $this->location->update(['is_active' => true]);
    $inactive = InventoryLocation::factory()->create(['is_active' => false]);
    $levels = [];

    foreach ([[$this->location, 10, 8], [$this->location, 10, 4], [$this->location, 20, 0], [$inactive, 10, 10]] as [$location, $onHand, $reserved]) {
        $item = InventoryItem::create(['name' => 'Dashboard stock']);
        $levels[] = InventoryLevel::factory()->create([
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'reorder_point' => 10,
        ]);
    }

    $rows = $this->report->getLowStockQuery()->get();

    expect($rows->modelKeys())->toBe([$levels[0]->id, $levels[1]->id])
        ->and($rows->first()->location->id)->toBe($this->location->id)
        ->and($rows->first()->inventoryable_id)->toBe($levels[0]->inventoryable_id)
        ->and($rows->first()->available)->toBe(2)
        ->and((int) $rows->first()->getAttribute('deficit'))->toBe(8)
        ->and((int) $rows->last()->getAttribute('deficit'))->toBe(4);
});
