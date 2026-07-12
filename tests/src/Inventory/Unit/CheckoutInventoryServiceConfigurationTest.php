<?php

declare(strict_types=1);

use AIArmada\Inventory\Integrations\CheckoutInventoryService;

it('uses only inventory model overrides for checkout integration', function (): void {
    config()->set('inventory.models.product', 'App\Models\InventoryProduct');
    config()->set('inventory.models.variant', 'App\Models\InventoryVariant');

    $service = app(CheckoutInventoryService::class);
    $reflection = new ReflectionClass(CheckoutInventoryService::class);

    expect($reflection->getMethod('getProductModelClass')->invoke($service))
        ->toBe('App\Models\InventoryProduct')
        ->and($reflection->getMethod('getVariantModelClass')->invoke($service))
        ->toBe('App\Models\InventoryVariant');
});
