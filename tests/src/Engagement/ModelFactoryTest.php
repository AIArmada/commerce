<?php

declare(strict_types=1);

use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;
use AIArmada\Engagement\Models\Subscription;

it('creates engagement models via factories', function (): void {
    $follow = Follow::factory()->create([
        'follower_type' => 'user',
        'follower_id' => 'user-1',
        'followable_type' => 'speaker',
        'followable_id' => 'speaker-1',
    ]);
    expect($follow->exists)->toBeTrue();

    $bookmark = Bookmark::factory()->create([
        'bookmarker_type' => 'user',
        'bookmarker_id' => 'user-1',
        'bookmarkable_type' => 'event',
        'bookmarkable_id' => 'event-1',
    ]);
    expect($bookmark->exists)->toBeTrue();

    $response = Response::factory()->create([
        'responder_type' => 'user',
        'responder_id' => 'user-1',
        'respondable_type' => 'event_occurrence',
        'respondable_id' => 'occ-1',
    ]);
    expect($response->exists)->toBeTrue();

    $subscription = Subscription::factory()->create([
        'subscriber_type' => 'user',
        'subscriber_id' => 'user-1',
        'subscribable_type' => 'event_occurrence',
        'subscribable_id' => 'occ-1',
    ]);
    expect($subscription->exists)->toBeTrue();

    $reminder = Reminder::factory()->create([
        'remindable_type' => 'event_occurrence',
        'remindable_id' => 'occ-1',
        'recipient_type' => 'user',
        'recipient_id' => 'user-1',
    ]);
    expect($reminder->exists)->toBeTrue();

    $share = Share::factory()->create([
        'shareable_type' => 'event',
        'shareable_id' => 'event-1',
    ]);
    expect($share->exists)->toBeTrue();
});
