<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Actions\UpdateInventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

            public function resolve(): ?RecordReference
            {
                return EloquentRecordReference::fromModel($this->owner);
            }
        });

        // Resolve the trusted tenant before measuring business queries.
        OwnerContext::resolve();

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

it('does not allow tenants to mutate visible global stock', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $owner = InventoryItem::create(['name' => 'Tenant']);
    $sku = InventoryItem::create(['name' => 'Stock item']);
    [$location, $level] = OwnerContext::withOwner(null, function () use ($sku): array {
        $location = InventoryLocation::getOrCreateDefault();
        app(InventoryService::class)->receive($sku, $location->id, 10);

        return [$location, InventoryLevel::query()->where('location_id', $location->id)->firstOrFail()];
    });

    OwnerContext::withOwner($owner, function () use ($sku, $location, $level): void {
        expect(InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())->whereKey($level->id)->exists())->toBeTrue()
            ->and(InventoryOwnerScope::forMutation(InventoryLevel::query())->whereKey($level->id)->exists())->toBeFalse();

        expect(fn () => app(InventoryService::class)->receive($sku, $location->id, 5))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => app(UpdateInventoryLevel::class)->execute($level, ['quantity_on_hand' => 99]))
            ->toThrow(ModelNotFoundException::class);

        $default = InventoryLocation::getOrCreateDefault();
        expect($default->id)->not->toBe($location->id);
    });

    OwnerContext::withOwner(null, function () use ($level): void {
        expect($level->fresh()->quantity_on_hand)->toBe(10);
    });
});
