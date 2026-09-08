<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
});

it('creates a bookmark', function (): void {
    $bookmark = $this->manager->bookmark($this->actor, $this->subject);

    expect($bookmark->status)->toBe(BookmarkStatus::Active);
});

it('removes bookmark via status', function (): void {
    $this->manager->bookmark($this->actor, $this->subject);
    $this->manager->removeBookmark($this->actor, $this->subject);

    expect(Bookmark::query()->where('status', 'removed')->count())->toBe(1);
});
