<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Persons;

use AIArmada\Commerce\Tests\TestCase;

abstract class PersonsTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/persons/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            \AIArmada\Persons\PersonsServiceProvider::class,
        ]);
    }
}
