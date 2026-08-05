<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentOrganizations;

use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\FilamentOrganizations\FilamentOrganizationsServiceProvider;

abstract class FilamentOrganizationsTestCase extends OrganizationsTestCase
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FilamentOrganizationsServiceProvider::class,
        ];
    }
}
