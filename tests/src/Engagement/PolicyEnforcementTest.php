<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    app()->instance(EngagementPolicyResolver::class, new class implements EngagementPolicyResolver
    {
        public function canFollow(mixed $actor, mixed $subject): bool
        {
            return false;
        }

        public function canBookmark(mixed $actor, mixed $subject): bool
        {
            return false;
        }

        public function canRespond(mixed $actor, mixed $subject, string $responseType): bool
        {
            return false;
        }

        public function canReact(mixed $actor, mixed $subject, string $reactionType): bool
        {
            return false;
        }

        public function canSubscribe(mixed $actor, mixed $subject = null, string $subscriptionType = 'updates'): bool
        {
            return false;
        }

        public function canSetReminder(mixed $actor, mixed $subject, string $reminderType): bool
        {
            return false;
        }
    });

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
            return 'subject';
        }

        public function getKey(): string
        {
            return 'subject-1';
        }
    };
});

it('enforces every engagement policy decision', function (): void {
    $manager = app(EngagementManager::class);
    $subscriptions = app(SubscriptionManager::class);
    $reminders = app(ReminderManager::class);

    expect(fn () => $manager->follow($this->actor, $this->subject))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $manager->bookmark($this->actor, $this->subject))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $manager->respond($this->actor, $this->subject, 'going'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $manager->react($this->actor, $this->subject, 'like'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $subscriptions->subscribe($this->actor, $this->subject))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $reminders->setReminder($this->actor, $this->subject, 'follow_up'))
        ->toThrow(AuthorizationException::class);
});
