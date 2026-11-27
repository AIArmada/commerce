<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Support;

use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Features\Interactions\Models\Share;
use AIArmada\Engagement\Features\Reminders\Models\Reminder;
use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;

final class ModelResolver
{
    public static function followClass(): string
    {
        return config('engagement.models.follow', Follow::class);
    }

    public static function bookmarkClass(): string
    {
        return config('engagement.models.bookmark', Bookmark::class);
    }

    public static function responseClass(): string
    {
        return config('engagement.models.response', Response::class);
    }

    public static function reactionClass(): string
    {
        return config('engagement.models.reaction', Reaction::class);
    }

    public static function subscriptionClass(): string
    {
        return config('engagement.models.subscription', Subscription::class);
    }

    public static function reminderClass(): string
    {
        return config('engagement.models.reminder', Reminder::class);
    }

    public static function shareClass(): string
    {
        return config('engagement.models.share', Share::class);
    }
}
