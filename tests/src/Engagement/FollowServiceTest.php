<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Follow;

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
            return 'speaker';
        }

        public function getKey(): string
        {
            return 'speaker-1';
        }
    };
});

it('creates a follow', function (): void {
    $follow = $this->manager->follow($this->actor, $this->subject);

    expect($follow->status)->toBe(Follow::STATUS_ACTIVE);
});

it('prevents duplicate active follows', function (): void {
    $this->manager->follow($this->actor, $this->subject);
    $second = $this->manager->follow($this->actor, $this->subject);

    expect($second->status)->toBe(Follow::STATUS_ACTIVE);
});

it('unfollows without deleting', function (): void {
    $this->manager->follow($this->actor, $this->subject);
    $this->manager->unfollow($this->actor, $this->subject);

    $follow = Follow::query()->first();
    expect($follow->status)->toBe('unfollowed');
});
