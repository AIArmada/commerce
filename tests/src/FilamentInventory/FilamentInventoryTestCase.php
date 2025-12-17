<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentInventory;

use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\FilamentInventory\FilamentInventoryServiceProvider;

abstract class FilamentInventoryTestCase extends InventoryTestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        $providers[] = FilamentInventoryServiceProvider::class;

        return $providers;
    }
}
