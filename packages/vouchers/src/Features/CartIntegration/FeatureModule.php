<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration;

use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\Listeners\ValidateVoucherOnCheckout;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        $event = 'AIArmada\\Checkout\\Events\\CheckoutStarted';

        if (class_exists($event)) {
            $app->make(Dispatcher::class)->listen($event, ValidateVoucherOnCheckout::class);
        }
    }
}
