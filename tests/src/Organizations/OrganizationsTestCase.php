<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Organizations;

use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use AIArmada\Membership\MembershipServiceProvider;
use AIArmada\Organizations\OrganizationsServiceProvider;

abstract class OrganizationsTestCase extends BaseTestCase
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            MembershipServiceProvider::class,
            OrganizationsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/organizations/database/migrations');
    }
}
