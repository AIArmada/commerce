<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments;

use AIArmada\Orders\Contracts\CancellationRefundReservations;
use AIArmada\Orders\Contracts\OrderRefunds;
use AIArmada\Orders\Features\Payments\Actions\OrderRefundOperations;
use AIArmada\Orders\Features\Payments\Actions\RegisterOrderRefund;
use AIArmada\Orders\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CancellationRefundReservations::class, RegisterOrderRefund::class);
        $app->bind(OrderRefunds::class, OrderRefundOperations::class);
    }

    public function boot(Application $app): void {}
}
