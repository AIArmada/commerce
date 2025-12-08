<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentStock;

use AIArmada\Commerce\Tests\TestCase;

abstract class FilamentStockTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../../packages/stock/database/migrations');
    }
}
