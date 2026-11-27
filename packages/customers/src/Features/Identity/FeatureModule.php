<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity;

use AIArmada\Customers\Features\Identity\Actions\CleanupCustomerDependents;
use AIArmada\Customers\Features\Identity\Actions\CreateCustomer;
use AIArmada\Customers\Features\Identity\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Features\Identity\Actions\LinkCustomerToPerson;
use AIArmada\Customers\Features\Identity\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Features\Identity\Actions\MergeCustomers;
use AIArmada\Customers\Features\Identity\Api\CustomerDependentsCleaner;
use AIArmada\Customers\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CustomerDependentsCleaner::class, CleanupCustomerDependents::class);
        $app->bind(CreateCustomer::class);
        $app->bind(UpdateCustomerProfile::class);
        $app->bind(LinkCustomerToPerson::class);
        $app->bind(SetDefaultCustomerAddress::class);
        $app->bind(MergeCustomers::class);
    }

    public function boot(Application $app): void {}
}
