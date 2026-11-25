<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\LinksServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\TouchpointReader::class, Queries\ReadTouchpoints::class);
        $app->bind(Api\AttributionActivity::class, Actions\TouchAffiliateAttribution::class);
        $app->bind(Api\AttributionSignalReader::class, Queries\AttributionSignalQuery::class);
        $app->singleton(Api\AttributionDistribution::class, Queries\DistributeAttribution::class);
        $app->tag([
            Extensions\LastTouchAttribution::class,
            Extensions\FirstTouchAttribution::class,
            Extensions\LinearAttribution::class,
        ], 'affiliates.attribution_strategy');
        $app->bind(Api\AttributionRemoval::class, Actions\DeleteAttributions::class);
        $app->bind(Api\AttributionTracking::class, Actions\TrackAffiliateVisit::class);
        $app->bind(Api\CartAttribution::class, Actions\AttachAffiliateToCart::class);
        $app->bind(Api\TrackingLinkAdministration::class, Actions\ManageTrackingLinks::class);
    }

    public function boot(Application $app): void
    {
        if (class_exists(LinksServiceProvider::class)) {
            $app->tag([Extensions\AffiliateLinkGate::class], LinkGateInterface::class);
            Event::listen(LinkClicked::class, EntryPoints\Listeners\IncrementAffiliateLinkClicks::class);
        }
    }
}
