<?php

declare(strict_types=1);

use AIArmada\Inventory\Features\Reservations\Facades\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Facades\Inventory;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;

it('discovers the canonical inventory facades', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 4) . '/packages/inventory/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['extra']['laravel']['aliases'])->toBe([
        'Inventory' => Inventory::class,
        'InventoryAllocation' => InventoryAllocation::class,
    ])->and(Inventory::getFacadeRoot())->toBe(app(InventoryService::class))
        ->and(InventoryAllocation::getFacadeRoot())->toBe(app(InventoryAllocationService::class));
});
