<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions;

use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\Engagement\Features\Subscriptions\Api\ReadsSubscriptionState;
use AIArmada\Engagement\Features\Subscriptions\Api\WritesSubscriptionRecords;
use AIArmada\Engagement\Features\Subscriptions\Queries\ReadSubscriptionStateQuery;
use AIArmada\Engagement\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(WritesSubscriptionRecords::class, ManageSubscriptionsAction::class);
        $app->bind(ReadsSubscriptionState::class, ReadSubscriptionStateQuery::class);
    }

    public function boot(Application $app): void {}
}
