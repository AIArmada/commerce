<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->level = InventoryLevel::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 10,
    ]);
});

describe('HasInventory trait', function (): void {
    describe('inventoryLevels relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->inventoryLevels())->toBeInstanceOf(MorphMany::class);
        });

        it('returns inventory levels for the model', function (): void {
            $levels = $this->item->inventoryLevels;

            expect($levels)->toHaveCount(1);
            expect($levels->first()->id)->toBe($this->level->id);
        });

        it('orders by quantity_on_hand descending', function (): void {
            $location2 = InventoryLocation::factory()->create();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 200,
            ]);

            $levels = $this->item->inventoryLevels;

            expect($levels->first()->quantity_on_hand)->toBe(200);
            expect($levels->last()->quantity_on_hand)->toBe(100);
        });
    });

    describe('inventoryMovements relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->inventoryMovements())->toBeInstanceOf(MorphMany::class);
        });

        it('returns movements ordered by occurred_at desc', function (): void {
            $movement1 = InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => null,
                'to_location_id' => $this->location->id,
                'occurred_at' => now()->subHour(),
            ]);

            $movement2 = InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => null,
                'to_location_id' => $this->location->id,
                'occurred_at' => now(),
            ]);

            $movements = $this->item->inventoryMovements;

            expect($movements->first()->id)->toBe($movement2->id);
        });
    });

    describe('getTotalOnHand', function (): void {
        it('returns total quantity on hand', function (): void {
            expect($this->item->getTotalOnHand())->toBe(100);
        });

        it('sums across multiple locations', function (): void {
            $location2 = InventoryLocation::factory()->create();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            expect($this->item->getTotalOnHand())->toBe(150);
        });
    });

    describe('getTotalAvailable', function (): void {
        it('returns available quantity minus reserved', function (): void {
            expect($this->item->getTotalAvailable())->toBe(90); // 100 - 10 reserved
        });
    });

    describe('hasInventory', function (): void {
        it('returns true when sufficient inventory', function (): void {
            expect($this->item->hasInventory(50))->toBeTrue();
        });

        it('returns false when insufficient inventory', function (): void {
            expect($this->item->hasInventory(200))->toBeFalse();
        });

        it('defaults to checking for 1', function (): void {
            expect($this->item->hasInventory())->toBeTrue();
        });
    });

    describe('getInventoryAtLocation', function (): void {
        it('returns level for specific location', function (): void {
            $level = $this->item->getInventoryAtLocation($this->location->id);

            expect($level)->not->toBeNull();
            expect($level->id)->toBe($this->level->id);
        });

        it('returns null for nonexistent location', function (): void {
            expect($this->item->getInventoryAtLocation('nonexistent'))->toBeNull();
        });
    });

    describe('getAvailability', function (): void {
        it('returns availability by location', function (): void {
            $availability = $this->item->getAvailability();

            expect($availability)->toBeArray();
            expect($availability[$this->location->id])->toBe(90);
        });
    });

    describe('getAllocationStrategy', function (): void {
        it('returns null by default', function (): void {
            expect($this->item->getAllocationStrategy())->toBeNull();
        });
    });

    it('keeps stock mutation and cart allocation APIs on services', function (): void {
        expect(method_exists($this->item, 'receive'))->toBeFalse()
            ->and(method_exists($this->item, 'allocate'))->toBeFalse()
            ->and(method_exists($this->item, 'release'))->toBeFalse();
    });

    describe('getInventoryHistory', function (): void {
        it('returns movement history', function (): void {
            InventoryMovement::factory()->count(2)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'to_location_id' => $this->location->id,
            ]);

            $history = $this->item->getInventoryHistory();

            expect($history)->toBeInstanceOf(Collection::class);
            expect($history->count())->toBeGreaterThanOrEqual(2);
        });

        it('respects limit parameter', function (): void {
            for ($i = 0; $i < 5; $i++) {
                InventoryMovement::factory()->create([
                    'inventoryable_type' => $this->item->getMorphClass(),
                    'inventoryable_id' => $this->item->getKey(),
                    'to_location_id' => $this->location->id,
                ]);
            }

            $history = $this->item->getInventoryHistory(2);

            expect($history)->toHaveCount(2);
        });
    });

    describe('isLowInventory', function (): void {
        it('returns false when above threshold', function (): void {
            expect($this->item->isLowInventory(50))->toBeFalse();
        });

        it('returns true when below threshold', function (): void {
            $this->level->update(['quantity_on_hand' => 5]);

            expect($this->item->isLowInventory(10))->toBeTrue();
        });

        it('uses default threshold from config', function (): void {
            config(['inventory.default_reorder_point' => 200]);

            expect($this->item->isLowInventory())->toBeTrue();
        });
    });

});
