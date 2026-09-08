<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Enums\SubscriptionStatus;
use AIArmada\Engagement\Events\ReminderDue;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Subscription;
use Illuminate\Auth\Access\AuthorizationException;

it('rejects foreign owner mutations and reminder events', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner Write A',
        'email' => 'owner-write-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Owner Write B',
        'email' => 'owner-write-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $subscription = OwnerContext::withOwner($ownerB, function () use ($ownerB): Subscription {
        return Subscription::factory()->create([
            'subscriber_type' => $ownerB->getMorphClass(),
            'subscriber_id' => $ownerB->getKey(),
        ]);
    });
    $reminder = OwnerContext::withOwner($ownerB, function () use ($ownerB): Reminder {
        return Reminder::factory()->create([
            'remindable_type' => $ownerB->getMorphClass(),
            'remindable_id' => $ownerB->getKey(),
            'recipient_type' => $ownerB->getMorphClass(),
            'recipient_id' => $ownerB->getKey(),
            'status' => ReminderStatus::Pending,
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($reminder, $subscription): void {
        expect(fn (): Subscription => app(SubscriptionManager::class)->muteSubscription($subscription))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): Subscription => app(SubscriptionManager::class)->unmuteSubscription($subscription))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): mixed => app(ReminderManager::class)->markSent($reminder))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): mixed => app(ReminderManager::class)->markFailed($reminder, 'spoofed'))
            ->toThrow(AuthorizationException::class)
            ->and(fn (): mixed => event(new ReminderDue($reminder)))
            ->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner($ownerB, function () use ($reminder, $subscription): void {
        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($reminder->fresh()->status)->toBe(ReminderStatus::Pending);
    });
});
