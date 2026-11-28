<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes;

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Features\Passes\Api\PassesApi;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\IssuePassesOnFulfillment;
use AIArmada\Ticketing\Features\Passes\EntryPoints\Listeners\SendTransferNotifications;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Persistence\PassIssuer;
use AIArmada\Ticketing\Features\Passes\Persistence\PassTransferWriter;
use AIArmada\Ticketing\Features\Passes\Policies\PassTransferPolicy;
use AIArmada\Ticketing\Features\Passes\Queries\EloquentPassesApi;
use AIArmada\Ticketing\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Ticketing\Support\Integration\TicketingIntegration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PassesApi::class, EloquentPassesApi::class);
        $app->bind(PassTransferServiceInterface::class, PassTransferWriter::class);
        $app->bind(PassIssuerInterface::class, PassIssuer::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(Pass::class, PassTransferPolicy::class);

        if (config('ticketing.notifications.transfer.enabled', true)) {
            Event::listen(PassTransferred::class, SendTransferNotifications::class);
        }

        if (config('ticketing.features.auto_issue_passes', true) && TicketingIntegration::ordersAvailable() && class_exists(OrderFulfillmentRequired::class)) {
            Event::listen(OrderFulfillmentRequired::class, IssuePassesOnFulfillment::class);
        }
    }
}
