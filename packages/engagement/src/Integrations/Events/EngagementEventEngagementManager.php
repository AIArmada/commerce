<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Integrations\Events;

use AIArmada\Events\Contracts\EventEngagementManager as EventEngagementManagerContract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Contracts\SubscriptionManager;

final class EngagementEventEngagementManager implements EventEngagementManagerContract
{
    public function __construct(
        private readonly EngagementManager $engagementManager,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly ReminderManager $reminderManager,
        private readonly EngagementStateResolver $stateResolver,
        private readonly ShareUrlGenerator $shareUrlGenerator,
    ) {}

    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return $this->engagementManager->follow($actor, $eventTarget, $options);
    }

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return $this->engagementManager->bookmark($actor, $eventTarget, $options);
    }

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed
    {
        return $this->engagementManager->respond($actor, $eventTarget, $responseType, $options);
    }

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed
    {
        return $this->subscriptionManager->subscribe(
            $actor,
            $eventTarget,
            $options['subscription_type'] ?? 'updates',
            $options['criteria'] ?? [],
            $options,
        );
    }

    public function remind(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return $this->reminderManager->setReminder(
            $actor,
            $eventTarget,
            $options['reminder_type'] ?? 'default',
            $options,
        );
    }

    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        return $this->shareUrlGenerator->generateShareUrl($eventTarget, $options);
    }

    public function stateFor(mixed $actor, mixed $eventTarget): array
    {
        $response = $this->stateResolver->responseFor($actor, $eventTarget);

        return [
            'is_following' => $this->stateResolver->isFollowing($actor, $eventTarget),
            'is_bookmarked' => $this->stateResolver->isBookmarked($actor, $eventTarget),
            'response' => $response?->response_type,
            'subscriptions' => $this->stateResolver->subscriptionsFor($actor, $eventTarget),
            'reminders' => $this->stateResolver->remindersFor($actor, $eventTarget),
        ];
    }
}
