<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions;

use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Interactions\Queries\ReadInteractionStateQuery;
use AIArmada\Engagement\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReadsInteractionState::class, ReadInteractionStateQuery::class);
    }

    public function boot(Application $app): void {}
}
