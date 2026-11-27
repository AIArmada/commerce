<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Tracking;

use AIArmada\Communications\Features\Tracking\Actions\DeleteDeliveryTrackingTokens;
use AIArmada\Communications\Features\Tracking\Api\DeliveryTrackingCleaner;
use AIArmada\Communications\Features\Tracking\Queries\TrackingRelationModelResolver;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Communications\Runtime\TrackingRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(TrackingRelationModel::class, TrackingRelationModelResolver::class);
        $app->bind(DeliveryTrackingCleaner::class, DeleteDeliveryTrackingTokens::class);
    }

    public function boot(Application $app): void {}
}
