<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery;

use AIArmada\Communications\Features\Delivery\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Features\Delivery\Actions\CleanupDeliveryDependents;
use AIArmada\Communications\Features\Delivery\Actions\ManageScheduledDeliveries;
use AIArmada\Communications\Features\Delivery\Actions\PruneDeliveryData;
use AIArmada\Communications\Features\Delivery\Actions\RecordDeliveryTrackingEvent;
use AIArmada\Communications\Features\Delivery\Actions\ReplayWebhookEvent;
use AIArmada\Communications\Features\Delivery\Api\DeliveryDependentsCleaner;
use AIArmada\Communications\Features\Delivery\Api\DeliveryRetention;
use AIArmada\Communications\Features\Delivery\Api\ProviderEventProcessor;
use AIArmada\Communications\Features\Delivery\Api\ScheduledDeliveryLifecycle;
use AIArmada\Communications\Features\Delivery\Api\TrackingDeliveryEvents;
use AIArmada\Communications\Features\Delivery\Api\WebhookReplay;
use AIArmada\Communications\Features\Delivery\Queries\DeliveryRelationModelsResolver;
use AIArmada\Communications\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Communications\Runtime\DeliveryRelationModels;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(TrackingDeliveryEvents::class, RecordDeliveryTrackingEvent::class);
        $app->bind(DeliveryRelationModels::class, DeliveryRelationModelsResolver::class);
        $app->bind(DeliveryDependentsCleaner::class, CleanupDeliveryDependents::class);
        $app->bind(DeliveryRetention::class, PruneDeliveryData::class);
        $app->bind(ProviderEventProcessor::class, ApplyProviderEventAction::class);
        $app->bind(ScheduledDeliveryLifecycle::class, ManageScheduledDeliveries::class);
        $app->bind(WebhookReplay::class, ReplayWebhookEvent::class);
    }

    public function boot(Application $app): void {}
}
