<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate;

use AIArmada\Communications\Contracts\InboxCommunicationCreator;
use AIArmada\Communications\Features\Aggregate\Actions\CreateInboxCommunication;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(InboxCommunicationCreator::class, CreateInboxCommunication::class);
    }

    public function boot(Application $app): void {}
}
