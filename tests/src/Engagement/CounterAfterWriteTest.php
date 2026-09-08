<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->counters = app(EngagementCounterService::class);
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
});

it('updates cached counters inside manager write transactions', function (): void {
    $this->manager->follow($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'followers'))->toBe(1);

    $this->manager->muteFollow($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'followers'))->toBe(0);

    $this->manager->unmuteFollow($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'followers'))->toBe(1);

    $this->manager->unfollow($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'followers'))->toBe(0);
});

it('updates bookmark, response, and reaction counters after writes', function (): void {
    $this->manager->bookmark($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'bookmarks'))->toBe(1);

    $this->manager->removeBookmark($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'bookmarks'))->toBe(0);

    $this->manager->respond($this->actor, $this->subject, 'interested');
    expect($this->counters->value($this->subject, 'responses'))->toBe(1);

    $this->manager->cancelResponse($this->actor, $this->subject);
    expect($this->counters->value($this->subject, 'responses'))->toBe(0);

    $this->manager->react($this->actor, $this->subject, 'like');
    expect($this->counters->value($this->subject, 'reactions'))->toBe(1)
        ->and($this->counters->value($this->subject, 'reactions', 'like'))->toBe(1);

    $this->manager->removeReaction($this->actor, $this->subject, 'like');
    expect($this->counters->value($this->subject, 'reactions'))->toBe(0)
        ->and($this->counters->value($this->subject, 'reactions', 'like'))->toBe(0);
});
