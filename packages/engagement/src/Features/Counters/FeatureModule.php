<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Counters;

use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowMuted;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Events\FollowUnmuted;
use AIArmada\Engagement\Events\ReactionCreated;
use AIArmada\Engagement\Events\ReactionRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Features\Counters\Actions\UpdateEngagementCountersAction;
use AIArmada\Engagement\Features\Counters\Api\ReadsEngagementCounters;
use AIArmada\Engagement\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReadsEngagementCounters::class, UpdateEngagementCountersAction::class);
    }

    public function boot(Application $app): void
    {
        $service = $app->make(UpdateEngagementCountersAction::class);
        $dispatcher = $app->make(Dispatcher::class);

        $dispatcher->listen(FollowCreated::class, $service->onFollowCreated(...));
        $dispatcher->listen(FollowRemoved::class, $service->onFollowRemoved(...));
        $dispatcher->listen(FollowMuted::class, $service->onFollowMuted(...));
        $dispatcher->listen(FollowUnmuted::class, $service->onFollowUnmuted(...));
        $dispatcher->listen(BookmarkCreated::class, $service->onBookmarkCreated(...));
        $dispatcher->listen(BookmarkRemoved::class, $service->onBookmarkRemoved(...));
        $dispatcher->listen(BookmarkArchived::class, $service->onBookmarkArchived(...));
        $dispatcher->listen(ReactionCreated::class, $service->onReactionCreated(...));
        $dispatcher->listen(ReactionRemoved::class, $service->onReactionRemoved(...));
        $dispatcher->listen(ResponseCreated::class, $service->onResponseCreated(...));
        $dispatcher->listen(ResponseChanged::class, $service->onResponseChanged(...));
        $dispatcher->listen(ResponseCancelled::class, $service->onResponseCancelled(...));
    }
}
