<?php

declare(strict_types=1);

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
});

it('caches navigation badges instead of counting on every call', function (): void {
    Cache::flush();

    foreach ([
        InventoryLevelResource::class,
        InventoryLocationResource::class,
        InventoryAllocationResource::class,
        InventoryBatchResource::class,
    ] as $resource) {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $first = $resource::getNavigationBadge();
        $queriesAfterFirst = $queries;
        $second = $resource::getNavigationBadge();

        expect($second)->toBe($first)
            ->and($queries)->toBe($queriesAfterFirst);
    }
});
