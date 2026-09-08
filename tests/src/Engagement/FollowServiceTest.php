<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
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
