<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes;

use AIArmada\Ticketing\Features\TicketTypes\Api\TicketTypeApi;
use AIArmada\Ticketing\Features\TicketTypes\Queries\EloquentTicketTypeApi;
use AIArmada\Ticketing\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(TicketableTypeRegistry::class);
        $app->bind(TicketTypeApi::class, EloquentTicketTypeApi::class);
    }

    public function boot(Application $app): void {}
}
