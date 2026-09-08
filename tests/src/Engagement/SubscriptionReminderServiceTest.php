<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Enums\SubscriptionStatus;
use AIArmada\Engagement\Models\Subscription;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->subscriptionManager = app(SubscriptionManager::class);
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
});

it('creates a subscription', function (): void {
    $subscription = $this->subscriptionManager->subscribe($this->actor, $this->subject, 'updates');

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('unsubscribes via status change', function (): void {
    $this->subscriptionManager->subscribe($this->actor, $this->subject, 'updates');
    $this->subscriptionManager->unsubscribe($this->actor, $this->subject);

    expect(Subscription::query()->where('status', 'unsubscribed')->count())->toBe(1);
});

it('creates a reminder via engagement manager', function (): void {
    $reminder = $this->manager->remind($this->actor, $this->subject, [
        'reminder_type' => 'event',
        'remind_at' => now()->addHours(1),
    ]);

    expect($reminder->status)->toBe(ReminderStatus::Pending);
});

it('unmutes a subscription through the domain manager', function (): void {
    $subscription = $this->subscriptionManager->subscribe($this->actor, $this->subject);
    $this->subscriptionManager->muteSubscription($subscription);
    $this->subscriptionManager->unmuteSubscription($subscription);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->muted_at)->toBeNull();
});
