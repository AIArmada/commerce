<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

describe('InventoryOwnerScope retention', function (): void {
    it('InventoryOwnerScope class is retained', function (): void {
        expect(class_exists(InventoryOwnerScope::class))->toBeTrue();
    });

    it('applyToLocationQuery scopes locations by owner', function (): void {
        $scope = new ReflectionClass(InventoryOwnerScope::class);
        expect($scope->hasMethod('applyToLocationQuery'))->toBeTrue();
        expect($scope->hasMethod('applyToQueryByLocationRelation'))->toBeFalse();
        expect($scope->hasMethod('applyToMovementQuery'))->toBeTrue();
        expect($scope->hasMethod('cacheKeySuffix'))->toBeTrue();
    });

    it('preserves owner-scoped level results while removing relation predicates', function (): void {
        config()->set('inventory.owner.enabled', true);
        config()->set('inventory.owner.include_global', false);

        $ownerA = InventoryItem::create(['name' => 'Owner A']);
        $ownerB = InventoryItem::create(['name' => 'Owner B']);

        $locationA = OwnerContext::withOwner($ownerA, fn (): InventoryLocation => InventoryLocation::factory()->create([
            'code' => 'OWNER-A',
        ]));
        $locationB = OwnerContext::withOwner($ownerB, fn (): InventoryLocation => InventoryLocation::factory()->create([
            'code' => 'OWNER-B',
        ]));

        $levelA = OwnerContext::withOwner($ownerA, fn (): InventoryLevel => InventoryLevel::factory()->create([
            'location_id' => $locationA->id,
            'quantity_on_hand' => 5,
        ]));
        OwnerContext::withOwner($ownerB, fn (): InventoryLevel => InventoryLevel::factory()->create([
            'location_id' => $locationB->id,
            'quantity_on_hand' => 7,
        ]));

        app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
        {
            public function __construct(private readonly Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        DB::enableQueryLog();
        $legacyResult = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query))
            ->pluck('id')
            ->all();
        $legacyQueries = DB::getQueryLog();
        DB::flushQueryLog();

        $optimizedResult = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())->pluck('id')->all();
        $optimizedQueries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($legacyResult)->toEqual([$levelA->id])
            ->and($optimizedResult)->toEqual($legacyResult)
            ->and(count($legacyQueries))->toBe(1)
            ->and(count($optimizedQueries))->toBe(1)
            ->and(mb_strtolower($legacyQueries[0]['query']))->toContain('exists')
            ->and(mb_strtolower($optimizedQueries[0]['query']))->not->toContain('exists');
    });
});
