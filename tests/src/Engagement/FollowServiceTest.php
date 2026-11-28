<?php

declare(strict_types=1);

use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Features\Interactions\Actions\ManageFollowsAction;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->manager = app(ManageInteractionsAction::class);
    $this->actor = engagementActor();
    $this->subject = engagementSubject();
});

it('creates a follow', function (): void {
    $follow = $this->manager->follow($this->actor, $this->subject);

    expect($follow->status)->toBe(FollowStatus::Active);
});

it('prevents duplicate active follows', function (): void {
    Event::fake([FollowCreated::class]);

    $this->manager->follow($this->actor, $this->subject);
    $second = $this->manager->follow($this->actor, $this->subject);

    expect($second->status)->toBe(FollowStatus::Active);
    Event::assertDispatchedTimes(FollowCreated::class, 1);
});

it('unfollows without deleting', function (): void {
    $this->manager->follow($this->actor, $this->subject);
    $this->manager->unfollow($this->actor, $this->subject);

    $follow = Follow::query()->first();
    expect($follow->status)->toBe(FollowStatus::Unfollowed);
});

it('unfollows a muted follow without decrementing active follower counts twice', function (): void {
    $manager = app(ManageFollowsAction::class);
    $actor = engagementActor();
    $subject = engagementSubject();
    $follow = $manager->follow($actor, $subject);
    $manager->muteFollow($actor, $subject);
    $manager->unfollow($actor, $subject);

    expect($follow->fresh()->status)->toBe(FollowStatus::Unfollowed);
});
