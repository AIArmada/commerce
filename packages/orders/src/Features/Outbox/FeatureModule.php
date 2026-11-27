<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Outbox;

use AIArmada\Orders\Contracts\OrderOutboxWriter;
use AIArmada\Orders\Features\Outbox\Persistence\OrderOutbox;
use AIArmada\Orders\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(OrderOutboxWriter::class, OrderOutbox::class);
    }

    public function boot(Application $app): void {}
}
