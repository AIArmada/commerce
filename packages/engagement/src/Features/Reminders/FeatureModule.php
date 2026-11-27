<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders;

use AIArmada\Communications\CommunicationsServiceProvider;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Features\Reminders\EntryPoints\Listeners\DispatchReminderThroughCommunications;
use AIArmada\Engagement\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        if (class_exists(CommunicationsServiceProvider::class)) {
            $app->make(Dispatcher::class)->listen(ReminderDue::class, DispatchReminderThroughCommunications::class);
        }
    }

    public function boot(Application $app): void {}
}
