<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Stock\Queries\MovementAnalysisReport;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->report = new MovementAnalysisReport;
});

describe('MovementAnalysisReport', function (): void {
    describe('getMovementSummaryByType', function (): void {
        it('returns empty collection when no movements', function (): void {
            $summary = $this->report->getMovementSummaryByType();

            expect($summary)->toBeEmpty();
        });

        it('groups movements by type', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(5),
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => -50,
                'occurred_at' => now()->subDays(3),
            ]);

            $summary = $this->report->getMovementSummaryByType();

            expect($summary)->toHaveCount(2);
        });

        it('filters by location', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(5),
            ]);

            $summary = $this->report->getMovementSummaryByType(
                null,
                null,
                $this->location->id,
            );

            expect($summary)->not->toBeEmpty();
        });

        it('returns correct data structure', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(5),
            ]);

            $summary = $this->report->getMovementSummaryByType();

            expect($summary->first())->toHaveKeys([
                'movement_type',
                'count',
                'total_quantity',
                'total_value',
            ]);
        });
    });

    describe('getDailyMovementTrends', function (): void {
        it('returns data for each day in range', function (): void {
            $startDate = CarbonImmutable::now()->subDays(5);
            $endDate = CarbonImmutable::now();

            $trends = $this->report->getDailyMovementTrends($startDate, $endDate);

            expect($trends->count())->toBe(6);
        });

        it('returns correct data structure for each day', function (): void {
            $trends = $this->report->getDailyMovementTrends();

            expect($trends->first())->toHaveKeys([
                'date',
                'receipts',
                'shipments',
                'adjustments',
                'transfers',
            ]);
        });

        it('aggregates movements by day and type', function (): void {
            $date = CarbonImmutable::now()->subDays(2);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => $date,
            ]);

            $trends = $this->report->getDailyMovementTrends(
                $date->subDay(),
                CarbonImmutable::now(),
            );

            $dayData = $trends->firstWhere('date', $date->format('Y-m-d'));
            expect($dayData['receipts'])->toBe(100);
        });
    });

    describe('getTopMovers', function (): void {
        it('returns empty collection when no movements', function (): void {
            $movers = $this->report->getTopMovers();

            expect($movers)->toBeEmpty();
        });

        it('returns items sorted by movement count', function (): void {
            $item2 = InventoryItem::create(['name' => 'Item 2']);

            // Create more movements for item2
            for ($i = 0; $i < 5; $i++) {
                InventoryMovement::factory()->create([
                    'inventoryable_type' => $item2->getMorphClass(),
                    'inventoryable_id' => $item2->getKey(),
                    'from_location_id' => $this->location->id,
                    'type' => MovementType::Receipt,
                    'quantity' => 10,
                    'occurred_at' => now()->subDays($i + 1),
                ]);
            }

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 10,
                'occurred_at' => now()->subDay(),
            ]);

            $movers = $this->report->getTopMovers(10);

            expect($movers->first()['inventoryable_id'])->toBe($item2->getKey());
        });

        it('respects limit parameter', function (): void {
            for ($i = 0; $i < 5; $i++) {
                $item = InventoryItem::create(['name' => 'Item ' . $i]);
                InventoryMovement::factory()->create([
                    'inventoryable_type' => $item->getMorphClass(),
                    'inventoryable_id' => $item->getKey(),
                    'from_location_id' => $this->location->id,
                    'type' => MovementType::Receipt,
                    'quantity' => 10,
                    'occurred_at' => now()->subDay(),
                ]);
            }

            $movers = $this->report->getTopMovers(3);

            expect($movers)->toHaveCount(3);
        });

        it('returns correct data structure', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 10,
                'occurred_at' => now()->subDay(),
            ]);

            $movers = $this->report->getTopMovers();

            expect($movers->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'movement_count',
                'total_quantity_moved',
                'avg_quantity_per_movement',
            ]);
        });
    });

    describe('getSlowMovers', function (): void {
        it('returns items with stock but no recent movements', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(60),
            ]);

            $movers = $this->report->getSlowMovers(10, 30);

            expect($movers)->not->toBeEmpty();
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(45),
            ]);

            $movers = $this->report->getSlowMovers(10, 30);

            if ($movers->isNotEmpty()) {
                expect($movers->first())->toHaveKeys([
                    'inventoryable_type',
                    'inventoryable_id',
                    'current_quantity',
                    'last_movement_at',
                    'days_since_movement',
                ]);
            }
        });
    });

    describe('getMovementVelocity', function (): void {
        it('returns velocity metrics', function (): void {
            $velocity = $this->report->getMovementVelocity();

            expect($velocity)->toHaveKeys([
                'receipts_per_day',
                'shipments_per_day',
                'net_change_per_day',
                'days_of_stock_at_current_rate',
            ]);
        });

        it('calculates receipts per day', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Receipt,
                'quantity' => 100,
                'occurred_at' => now()->subDays(5),
            ]);

            $velocity = $this->report->getMovementVelocity();

            expect($velocity['receipts_per_day'])->toBeGreaterThan(0);
        });

        it('calculates days of stock', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => -10,
                'occurred_at' => now()->subDays(5),
            ]);

            $velocity = $this->report->getMovementVelocity();

            expect($velocity['days_of_stock_at_current_rate'])->toBeGreaterThanOrEqual(0);
        });
    });

    describe('getAdjustmentAnalysis', function (): void {
        it('returns empty collection when no adjustments', function (): void {
            $analysis = $this->report->getAdjustmentAnalysis();

            expect($analysis)->toBeEmpty();
        });

        it('groups adjustments by reason', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => -3,
                'reason' => 'damage',
                'occurred_at' => now()->subDays(3),
            ]);

            $analysis = $this->report->getAdjustmentAnalysis();

            expect($analysis)->toHaveCount(2);
        });

        it('returns correct data structure', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $analysis = $this->report->getAdjustmentAnalysis();

            expect($analysis->first())->toHaveKeys([
                'reason',
                'count',
                'total_positive',
                'total_negative',
                'net_adjustment',
            ]);
        });

        it('handles null reason as unknown', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => null,
                'occurred_at' => now()->subDays(5),
            ]);

            $analysis = $this->report->getAdjustmentAnalysis();

            expect($analysis->first()['reason'])->toBe('unknown');
        });
    });
});

it('reports actual adjustment direction and shipment velocity from stock actions', function (): void {
    $service = app(InventoryService::class);
    $service->receive($this->item, $this->location->id, 100);
    $service->adjust($this->item, $this->location->id, 80, 'cycle_count');
    $service->ship($this->item, $this->location->id, 10);

    $adjustment = $this->report->getAdjustmentAnalysis()->firstWhere('reason', 'cycle_count');

    expect($adjustment['total_positive'])->toBe(0)
        ->and($adjustment['total_negative'])->toBe(20)
        ->and($adjustment['net_adjustment'])->toBe(-20);

    $end = CarbonImmutable::now()->addSecond();
    $velocity = $this->report->getMovementVelocity($end->subDays(10), $end);

    expect($velocity['shipments_per_day'])->toBe(1.0)
        ->and($velocity['days_of_stock_at_current_rate'])->toBe(70.0);
});
