<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

describe('InventoryLocation', function (): void {
    it('can create inventory location', function (): void {
        $location = InventoryLocation::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        expect($location)->toBeInstanceOf(InventoryLocation::class);
        expect($location->name)->toBe('Main Warehouse');
        expect($location->code)->toBe('MAIN');
        expect($location->is_active)->toBeTrue();
    });

    it('can have parent location', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Main Building',
        ]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Section A',
            'parent_id' => $parent->id,
        ]);

        expect($child->parent)->not->toBeNull();
        expect($child->parent->id)->toBe($parent->id);
    });

    it('children relationship', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Main Building',
        ]);

        InventoryLocation::factory()->create([
            'name' => 'Section A',
            'parent_id' => $parent->id,
        ]);
        InventoryLocation::factory()->create([
            'name' => 'Section B',
            'parent_id' => $parent->id,
        ]);

        expect($parent->children)->toHaveCount(2);
    });

    it('inventory levels relationship', function (): void {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->inventoryLevels)->toHaveCount(1);
    });

    it('movements to relationship', function (): void {
        $location = InventoryLocation::factory()->create();
        $fromLocation = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryMovement::factory()->create([
            'to_location_id' => $location->id,
            'from_location_id' => $fromLocation->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->movementsTo)->toHaveCount(1);
    });

    it('movements from relationship', function (): void {
        $location = InventoryLocation::factory()->create();
        $toLocation = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryMovement::factory()->create([
            'from_location_id' => $location->id,
            'to_location_id' => $toLocation->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->movementsFrom)->toHaveCount(1);
    });

    it('scope active', function (): void {
        InventoryLocation::factory()->create(['is_active' => true]);
        InventoryLocation::factory()->create(['is_active' => true]);
        InventoryLocation::factory()->create(['is_active' => false]);

        $activeLocations = InventoryLocation::active()->get();

        expect($activeLocations)->toHaveCount(2);
    });

    it('is default', function (): void {
        $defaultLocation = InventoryLocation::getOrCreateDefault();
        $regularLocation = InventoryLocation::factory()->create(['code' => 'REGULAR']);

        expect($defaultLocation->isDefault())->toBeTrue();
        expect($regularLocation->isDefault())->toBeFalse();
    });

    it('is root returns true for top level', function (): void {
        $location = InventoryLocation::factory()->create(['parent_id' => null]);

        expect($location->isRoot())->toBeTrue();
    });

    it('is root returns false for children', function (): void {
        $parent = InventoryLocation::factory()->create();
        $child = InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($child->isRoot())->toBeFalse();
    });

    it('is leaf returns true for childless', function (): void {
        $location = InventoryLocation::factory()->create();

        expect($location->isLeaf())->toBeTrue();
    });

    it('is leaf returns false for parents', function (): void {
        $parent = InventoryLocation::factory()->create();
        InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($parent->isLeaf())->toBeFalse();
    });

    it('has no children for empty', function (): void {
        $location = InventoryLocation::factory()->create();

        expect($location->children)->toHaveCount(0);
    });

    it('has children when children exist', function (): void {
        $parent = InventoryLocation::factory()->create();
        InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($parent->children)->toHaveCount(1);
    });

    it('deleting location cascades to inventory levels', function (): void {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);
        $levelId = $level->id;

        $location->delete();

        expect(InventoryLevel::find($levelId))->toBeNull();
    });

    it('get descendants', function (): void {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child1 = InventoryLocation::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
        $child2 = InventoryLocation::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child1->id]);

        $descendants = $root->descendants;

        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('id')->toArray())->toContain($child1->id, $child2->id, $grandchild->id);
    });

    it('get ancestors', function (): void {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $ancestors = $grandchild->ancestors;

        expect($ancestors)->toHaveCount(2);
    });

    it('get breadcrumbs', function (): void {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $breadcrumbs = $grandchild->getBreadcrumbs();

        expect($breadcrumbs)->toHaveCount(3);
        expect($breadcrumbs->last()->id)->toBe($grandchild->id);
    });

    it('depth property', function (): void {
        $root = InventoryLocation::factory()->create();
        $child = InventoryLocation::factory()->create(['parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['parent_id' => $child->id]);

        expect($root->depth)->toBe(0);
        expect($child->depth)->toBe(1);
        expect($grandchild->depth)->toBe(2);
    });

    it('scope by priority', function (): void {
        $low = InventoryLocation::factory()->create(['priority' => 10]);
        $high = InventoryLocation::factory()->create(['priority' => 100]);
        $mid = InventoryLocation::factory()->create(['priority' => 50]);

        $sorted = InventoryLocation::byPriority()->get();

        expect($sorted->first()->id)->toBe($high->id);
        expect($sorted->last()->id)->toBe($low->id);
    });

    it('get or create default', function (): void {
        $default = InventoryLocation::getOrCreateDefault();

        expect($default)->toBeInstanceOf(InventoryLocation::class);
        expect($default->code)->toBe(InventoryLocation::DEFAULT_LOCATION_CODE);
        expect($default->is_active)->toBeTrue();

        // Calling again should return same location
        $default2 = InventoryLocation::getOrCreateDefault();
        expect($default2->id)->toBe($default->id);
    });

    it('get or create default requires explicit global context when owner mode is enabled', function (): void {
        config([
            'inventory.owner.enabled' => true,
            'inventory.owner.include_global' => false,
        ]);

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });

        expect(fn () => InventoryLocation::getOrCreateDefault())
            ->toThrow(RuntimeException::class, 'InventoryLocation::getOrCreateDefault() requires an owner context or explicit global context.');

        $default = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::getOrCreateDefault());

        expect($default->isDefault())->toBeTrue();
    });

    it('owner columns are not mass assignable', function (): void {
        config([
            'inventory.owner.enabled' => true,
            'inventory.owner.include_global' => false,
        ]);

        $owner = InventoryItem::create(['name' => 'Owner']);
        $otherOwner = InventoryItem::create(['name' => 'Other Owner']);

        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        $location = InventoryLocation::create([
            'code' => 'SAFE-OWNER',
            'name' => 'Safe Owner Location',
            'owner_type' => $otherOwner->getMorphClass(),
            'owner_id' => $otherOwner->getKey(),
        ]);

        expect($location->owner_type)->toBe($owner->getMorphClass())
            ->and((string) $location->owner_id)->toBe((string) $owner->getKey());
    });

    it('forged owner columns are rejected when set outside mass assignment', function (): void {
        config([
            'inventory.owner.enabled' => true,
            'inventory.owner.include_global' => false,
        ]);

        $owner = InventoryItem::create(['name' => 'Owner']);
        $otherOwner = InventoryItem::create(['name' => 'Other Owner']);

        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        expect(fn () => InventoryLocation::factory()->create([
            'code' => 'FORGED-OWNER',
            'owner_type' => $otherOwner->getMorphClass(),
            'owner_id' => $otherOwner->getKey(),
        ]))->toThrow(AuthorizationException::class, 'Cross-owner save blocked for AIArmada\Inventory\Models\InventoryLocation.');
    });

    it('has available capacity', function (): void {
        $withCapacity = InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 80,
        ]);
        $noCapacity = InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        expect($withCapacity->hasAvailableCapacity(15))->toBeTrue();
        expect($withCapacity->hasAvailableCapacity(25))->toBeFalse();
        expect($noCapacity->hasAvailableCapacity(1000))->toBeTrue();
    });

    it('get utilization percentage', function (): void {
        $location = InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 75,
        ]);
        $noCapacity = InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        expect($location->getUtilizationPercentage())->toBe(75.0);
        expect($noCapacity->getUtilizationPercentage())->toBeNull();
    });

    it('coordinates', function (): void {
        $location = InventoryLocation::factory()->create();
        $location->setCoordinates(10.5, 20.3, 5.0);
        $location->save();

        $coords = $location->getCoordinates();

        expect($coords['x'])->toBe('10.50');
        expect($coords['y'])->toBe('20.30');
        expect($coords['z'])->toBe('5.00');
    });

    it('distance to', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => 0,
            'coordinate_z' => 0,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 3,
            'coordinate_y' => 4,
            'coordinate_z' => 0,
        ]);

        $distance = $location1->distanceTo($location2);

        expect($distance)->toBe(5.0);
    });

    it('distance to returns null when no coordinates', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => null,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
        ]);

        expect($location1->distanceTo($location2))->toBeNull();
    });

    it('scope with temperature zone', function (): void {
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Chilled->value]);
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Frozen->value]);
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Chilled->value]);

        $chilled = InventoryLocation::withTemperatureZone(TemperatureZone::Chilled)->get();
        $frozen = InventoryLocation::withTemperatureZone(TemperatureZone::Frozen)->get();

        expect($chilled)->toHaveCount(2);
        expect($frozen)->toHaveCount(1);
    });

    it('scope hazmat certified', function (): void {
        InventoryLocation::factory()->create(['is_hazmat_certified' => true]);
        InventoryLocation::factory()->create(['is_hazmat_certified' => false]);
        InventoryLocation::factory()->create(['is_hazmat_certified' => true]);

        $hazmat = InventoryLocation::hazmatCertified()->get();

        expect($hazmat)->toHaveCount(2);
    });

    it('scope with available capacity', function (): void {
        // Location with capacity but full
        InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 100,
        ]);
        // Location with capacity and space
        InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 50,
        ]);
        // Location with unlimited capacity
        InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        $available = InventoryLocation::withAvailableCapacity(10)->get();

        expect($available)->toHaveCount(2); // One with space + one unlimited
    });

    it('scope by pick sequence', function (): void {
        $third = InventoryLocation::factory()->create(['pick_sequence' => 30]);
        $first = InventoryLocation::factory()->create(['pick_sequence' => 10]);
        $second = InventoryLocation::factory()->create(['pick_sequence' => 20]);

        $sorted = InventoryLocation::byPickSequence()->get();

        expect($sorted->first()->id)->toBe($first->id);
        expect($sorted->last()->id)->toBe($third->id);
    });

    it('get temperature zone enum', function (): void {
        $withZone = InventoryLocation::factory()->create([
            'temperature_zone' => TemperatureZone::Frozen->value,
        ]);
        $withoutZone = InventoryLocation::factory()->create([
            'temperature_zone' => null,
        ]);

        expect($withZone->getTemperatureZoneEnum())->toBe(TemperatureZone::Frozen);
        expect($withoutZone->getTemperatureZoneEnum())->toBeNull();
    });

    it('can store temperature zone', function (): void {
        $chilledLocation = InventoryLocation::factory()->create([
            'temperature_zone' => TemperatureZone::Chilled->value,
        ]);
        $noZoneLocation = InventoryLocation::factory()->create([
            'temperature_zone' => null,
        ]);

        // Same zone should be compatible
        expect($chilledLocation->canStoreTemperatureZone(TemperatureZone::Chilled))->toBeTrue();
        // Chilled is not compatible with Frozen
        expect($chilledLocation->canStoreTemperatureZone(TemperatureZone::Frozen))->toBeFalse();
        // No zone = ambient, only ambient is compatible
        expect($noZoneLocation->canStoreTemperatureZone(TemperatureZone::Ambient))->toBeTrue();
        expect($noZoneLocation->canStoreTemperatureZone(TemperatureZone::Chilled))->toBeFalse();
    });

    it('can store hazmat', function (): void {
        $certified = InventoryLocation::factory()->create(['is_hazmat_certified' => true]);
        $notCertified = InventoryLocation::factory()->create(['is_hazmat_certified' => false]);

        expect($certified->canStoreHazmat())->toBeTrue();
        expect($notCertified->canStoreHazmat())->toBeFalse();
    });

    it('allocations relationship', function (): void {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        InventoryAllocation::factory()->create([
            'location_id' => $location->id,
            'level_id' => $level->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'quantity' => 10,
        ]);

        expect($location->allocations)->toHaveCount(1);
    });

    it('deleting location cascades to allocations', function (): void {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        $allocation = InventoryAllocation::factory()->create([
            'location_id' => $location->id,
            'level_id' => $level->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'quantity' => 10,
        ]);
        $allocationId = $allocation->id;

        $location->delete();

        expect(InventoryAllocation::find($allocationId))->toBeNull();
    });

    it('scope for owner when disabled', function (): void {
        config(['inventory.owner.enabled' => false]);

        $location1 = InventoryLocation::factory()->create();
        $location2 = InventoryLocation::factory()->create();

        // When disabled, forOwner should return all records
        $result = InventoryLocation::forOwner(null)->get();

        expect($result)->toHaveCount(2);
    });

    it('scope for owner with owner enabled and null', function (): void {
        config([
            'inventory.owner.enabled' => true,
            'inventory.owner.include_global' => true,
        ]);

        // Create global location (no owner)
        $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create());

        // When owner is null and includeGlobal is true (default), return ownerless
        $result = InventoryLocation::forOwner(null, true)->get();

        expect($result->pluck('id')->toArray())->toContain($globalLocation->id);
    });

    it('scope for owner with owner model', function (): void {
        config([
            'inventory.owner.enabled' => true,
            'inventory.owner.include_global' => true,
        ]);

        $owner = InventoryItem::create(['name' => 'Owner Item']);

        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        // Create owned location
        $ownedLocation = InventoryLocation::factory()->create();

        // Create global location
        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
        $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create());

        // Create location owned by different owner
        $otherOwner = InventoryItem::create(['name' => 'Other Owner']);
        app()->instance(OwnerResolverInterface::class, new class($otherOwner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
        $otherLocation = InventoryLocation::factory()->create();

        // With includeGlobal true, should get owner's + global
        $result = InventoryLocation::withoutGlobalScopes()->forOwner($owner, true)->get();

        expect($result->pluck('id')->toArray())->toContain($ownedLocation->id);
        expect($result->pluck('id')->toArray())->toContain($globalLocation->id);
        expect($result->pluck('id')->toArray())->not->toContain($otherLocation->id);
    });

    it('scope for owner exclude global', function (): void {
        config(['inventory.owner.enabled' => true]);

        $owner = InventoryItem::create(['name' => 'Owner Item']);

        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        // Create owned location
        $ownedLocation = InventoryLocation::factory()->create();

        // Create global location
        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
        $globalLocation = OwnerContext::withOwner(null, fn (): InventoryLocation => InventoryLocation::factory()->create());

        // With includeGlobal false, should only get owner's
        $result = InventoryLocation::withoutGlobalScopes()->forOwner($owner, false)->get();

        expect($result->pluck('id')->toArray())->toContain($ownedLocation->id);
        expect($result->pluck('id')->toArray())->not->toContain($globalLocation->id);
    });

    it('get utilization percentage with zero capacity', function (): void {
        $location = InventoryLocation::factory()->create([
            'capacity' => 0,
            'current_utilization' => 0,
        ]);

        expect($location->getUtilizationPercentage())->toBeNull();
    });

    it('set coordinates returns self', function (): void {
        $location = InventoryLocation::factory()->create();

        $result = $location->setCoordinates(5.0, 10.0, 15.0);

        expect($result)->toBe($location);
        expect($location->coordinate_x)->toBe('5.00');
        expect($location->coordinate_y)->toBe('10.00');
        expect($location->coordinate_z)->toBe('15.00');
    });

    it('distance with null y and z', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => null,
            'coordinate_z' => null,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
            'coordinate_y' => null,
            'coordinate_z' => null,
        ]);

        $distance = $location1->distanceTo($location2);

        expect($distance)->toBe(10.0);
    });
});
