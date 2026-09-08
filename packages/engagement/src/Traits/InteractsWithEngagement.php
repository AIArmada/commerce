<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal actor-side implementation shared by the public Can* traits.
 *
 * @mixin Model
 */
trait InteractsWithEngagement
{
    protected function engagementActor(): CanInteract
    {
        return EngagementModelGuard::requireContract($this, CanInteract::class, 'actor');
    }

    protected function engagementManager(): EngagementManager
    {
        return app(EngagementManager::class);
    }

    protected function engagementStateResolver(): EngagementStateResolver
    {
        return app(EngagementStateResolver::class);
    }

    protected function engagementReminderManager(): ReminderManager
    {
        return app(ReminderManager::class);
    }

    protected function engagementSubscriptionManager(): SubscriptionManager
    {
        return app(SubscriptionManager::class);
    }
}
