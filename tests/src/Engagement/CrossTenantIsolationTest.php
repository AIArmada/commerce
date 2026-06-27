<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Enums\ReactionStatus;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Enums\SubscriptionStatus;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Models\BookmarkCollectionItem;
use AIArmada\Engagement\Models\EngagementCounter;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;
use AIArmada\Engagement\Models\Subscription;
use Illuminate\Auth\Access\AuthorizationException;

it('isolates bookmark collections by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Engagement Owner A',
        'email' => 'engagement-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Engagement Owner B',
        'email' => 'engagement-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $collectionA = OwnerContext::withOwner($ownerA, function (): BookmarkCollection {
        return BookmarkCollection::query()->create([
            'name' => 'Owner A Collection',
            'visibility' => 'private',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    });

    $collectionB = OwnerContext::withOwner($ownerB, function (): BookmarkCollection {
        return BookmarkCollection::query()->create([
            'name' => 'Owner B Collection',
            'visibility' => 'private',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    });

    $ownerACollectionIds = OwnerContext::withOwner($ownerA, function (): array {
        return BookmarkCollection::query()->pluck('id')->all();
    });

    expect($ownerACollectionIds)->toEqual([$collectionA->id]);

    expect(function () use ($ownerA, $collectionB): void {
        OwnerContext::withOwner($ownerA, function () use ($collectionB): void {
            OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collectionB->id);
        });
    })->toThrow(AuthorizationException::class);
});

it('isolates every tenant-owned engagement record type', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Engagement Record Owner A',
        'email' => 'engagement-record-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Engagement Record Owner B',
        'email' => 'engagement-record-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $models = [
        Follow::class => [
            'follower_type' => 'user',
            'follower_id' => fake()->uuid(),
            'followable_type' => 'subject',
            'followable_id' => fake()->uuid(),
            'status' => FollowStatus::Active,
        ],
        Bookmark::class => [
            'bookmarker_type' => 'user',
            'bookmarker_id' => fake()->uuid(),
            'bookmarkable_type' => 'subject',
            'bookmarkable_id' => fake()->uuid(),
            'status' => BookmarkStatus::Active,
        ],
        BookmarkCollectionItem::class => [
            'bookmark_collection_id' => fake()->uuid(),
            'bookmark_id' => fake()->uuid(),
        ],
        Response::class => [
            'responder_type' => 'user',
            'responder_id' => fake()->uuid(),
            'respondable_type' => 'subject',
            'respondable_id' => fake()->uuid(),
            'response_type' => 'going',
            'status' => ResponseStatus::Active,
            'visibility' => 'private',
        ],
        Reaction::class => [
            'reactor_type' => 'user',
            'reactor_id' => fake()->uuid(),
            'reactable_type' => 'subject',
            'reactable_id' => fake()->uuid(),
            'reaction_type' => 'like',
            'status' => ReactionStatus::Active,
        ],
        Subscription::class => [
            'subscriber_type' => 'user',
            'subscriber_id' => fake()->uuid(),
            'subscription_type' => 'updates',
            'status' => SubscriptionStatus::Active,
        ],
        Reminder::class => [
            'recipient_type' => 'user',
            'recipient_id' => fake()->uuid(),
            'remindable_type' => 'subject',
            'remindable_id' => fake()->uuid(),
            'reminder_type' => 'follow_up',
            'status' => ReminderStatus::Pending,
        ],
        Share::class => [
            'shareable_type' => 'subject',
            'shareable_id' => fake()->uuid(),
            'status' => ShareStatus::Created,
        ],
        EngagementCounter::class => [
            'subject_type' => 'subject',
            'subject_id' => fake()->uuid(),
            'counter_type' => 'reactions',
            'counter_key' => 'like',
            'count_value' => 1,
        ],
    ];

    foreach ($models as $modelClass => $attributes) {
        OwnerContext::withOwner($ownerA, fn () => $modelClass::query()->create($attributes));
        OwnerContext::withOwner($ownerB, fn () => $modelClass::query()->create($attributes));

        $ownerACount = OwnerContext::withOwner(
            $ownerA,
            fn (): int => $modelClass::query()->count(),
        );

        expect($ownerACount)->toBe(1, $modelClass);
    }
});

it('does not mass assign engagement owner columns', function (string $modelClass): void {
    $model = new $modelClass([
        'owner_type' => 'attacker',
        'owner_id' => 'attacker-id',
    ]);

    expect($model->owner_type)->toBeNull()
        ->and($model->owner_id)->toBeNull();
})->with([
    Follow::class,
    Bookmark::class,
    BookmarkCollection::class,
    BookmarkCollectionItem::class,
    Response::class,
    Reaction::class,
    Subscription::class,
    Reminder::class,
    Share::class,
    EngagementCounter::class,
]);
