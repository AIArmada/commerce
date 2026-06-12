<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Support;

final class ModelResolver
{
    public static function followClass(): string { return config('engagement.models.follow', \AIArmada\Engagement\Models\Follow::class); }
    public static function bookmarkClass(): string { return config('engagement.models.bookmark', \AIArmada\Engagement\Models\Bookmark::class); }
    public static function responseClass(): string { return config('engagement.models.response', \AIArmada\Engagement\Models\Response::class); }
    public static function reactionClass(): string { return config('engagement.models.reaction', \AIArmada\Engagement\Models\Reaction::class); }
    public static function subscriptionClass(): string { return config('engagement.models.subscription', \AIArmada\Engagement\Models\Subscription::class); }
    public static function reminderClass(): string { return config('engagement.models.reminder', \AIArmada\Engagement\Models\Reminder::class); }
    public static function shareClass(): string { return config('engagement.models.share', \AIArmada\Engagement\Models\Share::class); }
}
