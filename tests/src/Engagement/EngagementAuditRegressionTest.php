<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Features\Counters\Actions\UpdateEngagementCountersAction;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('rejects unsaved actors and subjects before any engagement write', function (): void {
    $manager = app(ManageInteractionsAction::class);
    $actor = engagementActor();
    $unsavedSubject = new EngagementSubject;
    $unsavedSubject->setAttribute($unsavedSubject->getKeyName(), (string) Str::uuid());

    expect(fn () => $manager->follow($actor, $unsavedSubject))
        ->toThrow(InvalidArgumentException::class, 'must be persisted');

    $unsavedActor = new EngagementActor;
    $unsavedActor->setAttribute($unsavedActor->getKeyName(), (string) Str::uuid());
    $subject = engagementSubject();

    expect(fn () => $manager->respond($unsavedActor, $subject, 'interested'))
        ->toThrow(InvalidArgumentException::class, 'must be persisted')
        ->and(Follow::query()->count())->toBe(0);
});

it('journals the counter partition from the event owner instead of the ambient owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $actor = engagementActor();
    $subject = engagementSubject();

    $event = new FollowCreated(
        (string) Str::uuid(),
        $actor->getMorphClass(),
        (string) $actor->getKey(),
        $subject->getMorphClass(),
        (string) $subject->getKey(),
        $ownerA->getMorphClass(),
        (string) $ownerA->getKey(),
    );

    OwnerContext::withOwner($ownerB, fn () => app(UpdateEngagementCountersAction::class)->onFollowCreated($event));

    $ownerAValue = OwnerContext::withOwner($ownerA, fn (): int => app(UpdateEngagementCountersAction::class)
        ->value($subject, 'followers'));
    $ownerBValue = OwnerContext::withOwner($ownerB, fn (): int => app(UpdateEngagementCountersAction::class)
        ->value($subject, 'followers'));

    expect($ownerAValue)->toBe(1)
        ->and($ownerBValue)->toBe(0);
});

it('clears muted_at and does not emit a second removal when unfollowing a muted follow', function (): void {
    Event::fake([FollowRemoved::class]);

    $manager = app(ManageInteractionsAction::class);
    $actor = engagementActor();
    $subject = engagementSubject();

    $follow = $manager->follow($actor, $subject);
    $manager->muteFollow($actor, $subject);
    $manager->unfollow($actor, $subject);

    expect($follow->fresh()->status)->toBe(FollowStatus::Unfollowed)
        ->and($follow->fresh()->muted_at)->toBeNull()
        ->and($follow->fresh()->unfollowed_at)->not->toBeNull();

    Event::assertNotDispatched(FollowRemoved::class);
});

it('keeps subscription matching scoped to the active owner partition', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $subscriber = engagementActor();
    $subject = engagementSubject();

    OwnerContext::withOwner($ownerA, fn () => app(ManageSubscriptionsAction::class)
        ->subscribe($subscriber, $subject, 'updates'));

    $matches = OwnerContext::withOwner($ownerB, fn (): array => iterator_to_array(
        app(ManageSubscriptionsAction::class)->matchingSubscriptions($subject, 'event_published'),
    ));

    expect($matches)->toBeEmpty();
});

it('requires persisted recipients and subjects for reminders', function (): void {
    $recipient = engagementActor();
    $unsaved = new EngagementSubject;
    $unsaved->setAttribute($unsaved->getKeyName(), (string) Str::uuid());

    expect(fn () => app(ManageRemindersAction::class)->setReminder($recipient, $unsaved, 'before_start', [
        'remind_at' => now()->addHour(),
    ]))->toThrow(InvalidArgumentException::class, 'must be persisted');
});
