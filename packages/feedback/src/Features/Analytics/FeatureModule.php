<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics;

use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Events\FeedbackResponseRejected;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Features\Analytics\Actions\DeleteFormAnalyticsAction;
use AIArmada\Feedback\Features\Analytics\Api\DeletesFormAnalytics;
use AIArmada\Feedback\Features\Analytics\EntryPoints\Listeners\QueueFeedbackAnalyticsRecalculation;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DeletesFormAnalytics::class, DeleteFormAnalyticsAction::class);
    }

    public function boot(Application $app): void
    {
        foreach ([
            FeedbackResponseStarted::class,
            FeedbackResponseSubmitted::class,
            FeedbackResponseReviewed::class,
            FeedbackResponseRejected::class,
            FeedbackResponseMarkedSpam::class,
        ] as $eventClass) {
            $app['events']->listen($eventClass, QueueFeedbackAnalyticsRecalculation::class);
        }
    }
}
