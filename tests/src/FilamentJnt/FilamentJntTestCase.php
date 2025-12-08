<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentJnt;

use AIArmada\Commerce\Tests\TestCase;

abstract class FilamentJntTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../../packages/jnt/database/migrations');
    }
}
