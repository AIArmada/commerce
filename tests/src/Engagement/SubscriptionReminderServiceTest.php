<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Subscription;

beforeEach(function () {
    $this->manager = app(EngagementManager::class);
    $this->subscriptionManager = app(SubscriptionManager::class);
    $this->actor = new class
    {
        public function getMorphClass(): string { return 'user'; }
        public function getKey(): string { return 'user-1'; }
    };
    $this->subject = new class
    {
        public function getMorphClass(): string { return 'event_occurrence'; }
        public function getKey(): string { return 'occ-1'; }
    };
});

it('creates a subscription', function () {
    $subscription = $this->subscriptionManager->subscribe($this->actor, $this->subject, 'updates');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE);
});

it('unsubscribes via status change', function () {
    $this->subscriptionManager->subscribe($this->actor, $this->subject, 'updates');
    $this->subscriptionManager->unsubscribe($this->actor, $this->subject);

    expect(Subscription::query()->where('status', 'unsubscribed')->count())->toBe(1);
});

it('creates a reminder via engagement manager', function () {
    $reminder = $this->manager->remind($this->actor, $this->subject, [
        'reminder_type' => 'event',
        'remind_at' => now()->addHours(1),
    ]);

    expect($reminder)->toBeInstanceOf(Reminder::class)
        ->and($reminder->status)->toBe(Reminder::STATUS_PENDING);
});
