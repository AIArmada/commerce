<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\PaymentMethods;

use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CashierChip\Features\PaymentMethods\Persistence\PaymentMethodStore;
use AIArmada\CashierChip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(PaymentMethodStoreInterface::class, PaymentMethodStore::class);
    }

    public function boot(Application $app): void {}
}
