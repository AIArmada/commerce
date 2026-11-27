<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Integrations\Events;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Features\Interactions\Api\WritesInteractionRecords;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Reminders\Api\WritesReminderRecords;
use AIArmada\Engagement\Features\Reminders\Api\ReadsReminderState;
use AIArmada\Engagement\Features\Subscriptions\Api\WritesSubscriptionRecords;
use AIArmada\Engagement\Features\Subscriptions\Api\ReadsSubscriptionState;
use AIArmada\Engagement\Support\EngagementModelGuard;
use AIArmada\Events\Contracts\EventEngagementManager as EventEngagementManagerContract;

final class EngagementEventEngagementManager implements EventEngagementManagerContract
{
    public function __construct(
        private readonly WritesInteractionRecords $engagementManager,
        private readonly WritesSubscriptionRecords $subscriptionManager,
        private readonly WritesReminderRecords $reminderManager,
        private readonly ReadsInteractionState $interactionState,
        private readonly ReadsSubscriptionState $subscriptionState,
        private readonly ReadsReminderState $reminderState,
    ) {}

    public function follow(mixed $actor, mixed $eventTarget, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Followable::class, 'event target');

        return $this->engagementManager->recordFollow($actor, $eventTarget, $options);
    }

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Bookmarkable::class, 'event target');

        return $this->engagementManager->recordBookmark($actor, $eventTarget, $options);
    }

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Respondable::class, 'event target');

        return $this->engagementManager->recordResponse($actor, $eventTarget, $responseType, $options);
    }

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = $eventTarget === null
            ? null
            : EngagementModelGuard::requireContract($eventTarget, Subscribable::class, 'event target');

        return $this->subscriptionManager->recordSubscription(
            $actor,
            $eventTarget,
            $options['subscription_type'] ?? 'updates',
            $options['criteria'] ?? [],
            $options,
        );
    }

    public function remind(mixed $actor, mixed $eventTarget, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Remindable::class, 'event target');

        return $this->reminderManager->recordReminder(
            $actor,
            $eventTarget,
            $options['reminder_type'] ?? 'default',
            $options,
        );
    }

    public function share(mixed $actor, mixed $eventTarget, array $options = []): array
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Shareable::class, 'event target');

        return $this->engagementManager->recordShare($actor, $eventTarget, $options);
    }

    public function stateFor(mixed $actor, mixed $eventTarget): array
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $eventTarget = EngagementModelGuard::requireModel($eventTarget, 'event target');
        $response = $this->interactionState->responseFor($actor, $eventTarget);

        return [
            'is_following' => $this->interactionState->isFollowing($actor, $eventTarget),
            'is_bookmarked' => $this->interactionState->isBookmarked($actor, $eventTarget),
            'response' => $response['response_type'] ?? null,
            'subscriptions' => $this->subscriptionState->subscriptionsFor($actor, $eventTarget),
            'reminders' => $this->reminderState->remindersFor($actor, $eventTarget),
            'share' => $this->interactionState->shareFor($actor, $eventTarget),
        ];
    }
}
