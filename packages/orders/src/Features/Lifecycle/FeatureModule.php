<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle;

use AIArmada\Orders\Contracts\OrderInvoiceNumber;
use AIArmada\Orders\Features\Lifecycle\Actions\AssignOrderInvoiceNumber;
use AIArmada\Orders\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(OrderInvoiceNumber::class, AssignOrderInvoiceNumber::class);
    }

    public function boot(Application $app): void {}
}
