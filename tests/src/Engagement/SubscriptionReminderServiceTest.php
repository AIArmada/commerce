<?php

declare(strict_types=1);

use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Enums\SubscriptionStatus;
use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

beforeEach(function (): void {
    $this->manager = app(ManageRemindersAction::class);
    $this->subscriptionManager = app(ManageSubscriptionsAction::class);
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

it('creates a reminder through the owning reminder action', function (): void {
    $reminder = $this->manager->setReminder($this->actor, $this->subject, 'event', [
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

it('cancels muted subscriptions without allowing unmute to resurrect them', function (): void {
    $subscription = $this->subscriptionManager->subscribe($this->actor, $this->subject);
    $this->subscriptionManager->muteSubscription($subscription);
    $this->subscriptionManager->unsubscribe($this->actor, $this->subject);
    $this->subscriptionManager->unmuteSubscription($subscription);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed);
});

it('excludes expired subscriptions and renews them on an explicit subscribe', function (): void {
    $subscription = $this->subscriptionManager->subscribe($this->actor, $this->subject);
    $subscription->update(['expires_at' => now()->subMinute()]);

    expect(iterator_to_array($this->subscriptionManager->matchingSubscriptions($this->subject, 'updated')))->toBe([]);

    $renewed = $this->subscriptionManager->subscribe($this->actor, $this->subject);
    expect($renewed->id)->toBe($subscription->id)
        ->and($renewed->expires_at)->toBeNull();
});
