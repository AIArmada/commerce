<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventoryStandardCost;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use OwenIt\Auditing\Contracts\Auditable;

it('inventory models are auditable and activity loggable', function (): void {
    $models = [
        InventoryLevel::class,
        InventoryMovement::class,
        InventoryAllocation::class,
        InventoryBatch::class,
        InventoryCostLayer::class,
        InventoryStandardCost::class,
        InventoryBackorder::class,
        InventoryLocation::class,
        InventorySerial::class,
        InventorySupplierLeadtime::class,
        InventoryReorderSuggestion::class,
        InventoryValuationSnapshot::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});
