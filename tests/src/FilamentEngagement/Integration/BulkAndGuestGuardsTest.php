<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Support\ModelResolver;
use AIArmada\FilamentEngagement\Actions\BookmarkAction;
use AIArmada\FilamentEngagement\Actions\FollowAction;
use AIArmada\FilamentEngagement\Actions\ReactAction;
use AIArmada\FilamentEngagement\Actions\RemoveBookmarkAction;
use AIArmada\FilamentEngagement\Actions\RespondAction;
use AIArmada\FilamentEngagement\Actions\SetReminderAction;
use AIArmada\FilamentEngagement\Actions\SubscribeAction;
use AIArmada\FilamentEngagement\Actions\UnfollowAction;
use AIArmada\FilamentEngagement\Support\AuthenticatedUser;
use AIArmada\FilamentEngagement\Support\BulkRecordProcessor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;

function engagementGuards_createFollow(User $follower, User $followable): Follow
{
    return OwnerContext::withOwner($follower, static fn (): Follow => Follow::query()->create([
        'follower_type' => $follower->getMorphClass(),
        'follower_id' => $follower->getKey(),
        'followable_type' => $followable->getMorphClass(),
        'followable_id' => $followable->getKey(),
        'status' => FollowStatus::Active,
    ]));
}

it('rejects guest callers across every reusable action', function (): void {
    $actor = User::query()->create([
        'name' => 'Guest Guard Actor',
        'email' => 'engagement-guest-actor@example.com',
        'password' => 'secret',
    ]);
    $subject = User::query()->create([
        'name' => 'Guest Guard Subject',
        'email' => 'engagement-guest-subject@example.com',
        'password' => 'secret',
    ]);

    $record = engagementGuards_createFollow($actor, $subject);

    $actions = [
        BookmarkAction::class,
        FollowAction::class,
        ReactAction::class,
        RemoveBookmarkAction::class,
        RespondAction::class,
        SetReminderAction::class,
        SubscribeAction::class,
        UnfollowAction::class,
    ];

    OwnerContext::withOwner($actor, function () use ($actions, $record): void {
        foreach ($actions as $actionClass) {
            $callback = $actionClass::make()->getActionFunction();

            expect($callback)->not->toBeNull();

            $arguments = in_array($actionClass, [
                ReactAction::class,
                RespondAction::class,
                SetReminderAction::class,
            ], true) ? [[], $record] : [null, $record];

            expect(fn (): mixed => $callback(...$arguments))
                ->toThrow(AuthenticationException::class);
        }
    });
});

it('resolves the signed-in user for action callers', function (): void {
    $user = User::query()->create([
        'name' => 'Signed In Actor',
        'email' => 'engagement-signed-in@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    expect(AuthenticatedUser::resolve()->is($user))->toBeTrue();
});

it('processes bulk selections in bounded chunks without dropping rows', function (): void {
    $actor = User::query()->create([
        'name' => 'Bulk Actor',
        'email' => 'engagement-bulk-actor@example.com',
        'password' => 'secret',
    ]);

    $records = [];
    for ($i = 0; $i < 5; $i++) {
        $subject = User::query()->create([
            'name' => "Bulk Subject {$i}",
            'email' => "engagement-bulk-subject-{$i}@example.com",
            'password' => 'secret',
        ]);
        $records[] = engagementGuards_createFollow($actor, $subject);
    }

    $processed = [];

    OwnerContext::withOwner($actor, function () use ($records, &$processed): void {
        BulkRecordProcessor::eachInChunks(
            $records,
            ModelResolver::followClass(),
            function (Follow $record) use (&$processed): void {
                $processed[] = (string) $record->getKey();
            },
            2,
        );
    });

    $expected = collect($records)->map(static fn (Follow $record): string => (string) $record->getKey())->sort()->values()->all();

    expect(collect($processed)->sort()->values()->all())->toBe($expected);
});

it('ignores an empty bulk selection', function (): void {
    $calls = 0;

    BulkRecordProcessor::eachInChunks([], ModelResolver::followClass(), function () use (&$calls): void {
        $calls++;
    });

    expect($calls)->toBe(0);
});

it('fails closed when a selected row leaves the owner scope', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Bulk Owner A',
        'email' => 'engagement-bulk-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Bulk Owner B',
        'email' => 'engagement-bulk-b@example.com',
        'password' => 'secret',
    ]);

    $foreign = engagementGuards_createFollow($ownerB, $ownerA);

    OwnerContext::withOwner($ownerA, static function () use ($foreign): void {
        BulkRecordProcessor::eachInChunks(
            [$foreign],
            ModelResolver::followClass(),
            static function (): void {},
        );
    });
})->throws(AuthorizationException::class);
