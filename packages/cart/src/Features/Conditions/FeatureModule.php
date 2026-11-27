<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Conditions;

use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Features\Conditions\EntryPoints\Listeners\ApplyGlobalConditions;
use AIArmada\Cart\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        if (! config('cart.events', true) || ! config('cart.conditions.apply_global', true)) {
            return;
        }

        $dispatcher = $app->make(Dispatcher::class);
        $dispatcher->listen(CartCreated::class, [ApplyGlobalConditions::class, 'handleCartCreated']);
        $dispatcher->listen(ItemAdded::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
        $dispatcher->listen(ItemUpdated::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
        $dispatcher->listen(ItemRemoved::class, [ApplyGlobalConditions::class, 'handleItemChanged']);
    }
}
