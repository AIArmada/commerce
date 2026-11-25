<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\AttributionActivity::class, Actions\TouchAffiliateAttribution::class);
        $app->bind(Api\AttributionRemoval::class, Actions\DeleteAttributions::class);
        $app->bind(Api\AttributionTracking::class, Actions\TrackAffiliateVisit::class);
        $app->bind(Api\CartAttribution::class, Actions\AttachAffiliateToCart::class);
        $app->bind(Api\TrackingLinkAdministration::class, Actions\ManageTrackingLinks::class);
    }

    public function boot(Application $app): void {}
}
