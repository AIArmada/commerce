<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes;

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Features\Passes\Persistence\PassIssuer;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\IssuePassesOnFulfillment;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\ReleaseSeatsOnPassCancelled;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\ReleaseSeatsOnPassExpired;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\ReleaseSeatsOnPassRevoked;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\ReleaseSeatsOnPassVoided;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Events\PassVoided;
use AIArmada\Ticketing\Support\Integration\TicketingIntegration;
use Illuminate\Support\Facades\Event;
use AIArmada\Ticketing\Features\Passes\Persistence\PassTransferWriter;
use AIArmada\Ticketing\Features\Passes\Policies\PassTransferPolicy;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PassTransferServiceInterface::class, PassTransferWriter::class);
        $app->bind(PassIssuerInterface::class, PassIssuer::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(Pass::class, PassTransferPolicy::class);

        if (config('ticketing.features.auto_issue_passes', true) && TicketingIntegration::ordersAvailable() && class_exists(OrderFulfillmentRequired::class)) {
            Event::listen(OrderFulfillmentRequired::class, IssuePassesOnFulfillment::class);
        }

        Event::listen(PassRevoked::class, ReleaseSeatsOnPassRevoked::class);
        Event::listen(PassCancelled::class, ReleaseSeatsOnPassCancelled::class);
        Event::listen(PassVoided::class, ReleaseSeatsOnPassVoided::class);
        Event::listen(PassExpired::class, ReleaseSeatsOnPassExpired::class);
    }
}
