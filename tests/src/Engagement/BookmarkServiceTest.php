<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Bookmark;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->actor = new class
    {
        public function getMorphClass(): string
        {
            return 'user';
        }

        public function getKey(): string
        {
            return 'user-1';
        }
    };
    $this->subject = new class
    {
        public function getMorphClass(): string
        {
            return 'event';
        }

        public function getKey(): string
        {
            return 'event-1';
        }
    };
});

it('creates a bookmark', function (): void {
    $bookmark = $this->manager->bookmark($this->actor, $this->subject);

    expect($bookmark->status)->toBe(Bookmark::STATUS_ACTIVE);
});

it('removes bookmark via status', function (): void {
    $this->manager->bookmark($this->actor, $this->subject);
    $this->manager->removeBookmark($this->actor, $this->subject);

    expect(Bookmark::query()->where('status', 'removed')->count())->toBe(1);
});
