<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Integrations\Events;

use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Models\Share;
use AIArmada\Engagement\Support\EngagementModelGuard;
use AIArmada\Events\Contracts\EventEngagementManager as EventEngagementManagerContract;

final class EngagementEventEngagementManager implements EventEngagementManagerContract
{
    public function __construct(
        private readonly EngagementManager $engagementManager,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly ReminderManager $reminderManager,
        private readonly EngagementStateResolver $stateResolver,
    ) {}

    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Followable::class, 'event target');

        return $this->engagementManager->follow($actor, $eventTarget, $options);
    }

    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Bookmarkable::class, 'event target');

        return $this->engagementManager->bookmark($actor, $eventTarget, $options);
    }

    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Respondable::class, 'event target');

        return $this->engagementManager->respond($actor, $eventTarget, $responseType, $options);
    }

    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = $eventTarget === null
            ? null
            : EngagementModelGuard::requireContract($eventTarget, Subscribable::class, 'event target');

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
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Remindable::class, 'event target');

        return $this->reminderManager->setReminder(
            $actor,
            $eventTarget,
            $options['reminder_type'] ?? 'default',
            $options,
        );
    }

    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed
    {
        $actor = EngagementModelGuard::requireContract($actor, CanInteract::class, 'actor');
        $eventTarget = EngagementModelGuard::requireContract($eventTarget, Shareable::class, 'event target');

        return $this->engagementManager->share($actor, $eventTarget, $options);
    }

    public function stateFor(mixed $actor, mixed $eventTarget): array
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $eventTarget = EngagementModelGuard::requireModel($eventTarget, 'event target');
        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $eventTargetIdentity = EngagementModelGuard::identity($eventTarget, 'event target');
        $response = $this->stateResolver->responseFor($actor, $eventTarget);

        $share = Share::query()
            ->where('sharer_type', $actorIdentity['type'])
            ->where('sharer_id', $actorIdentity['id'])
            ->where('shareable_type', $eventTargetIdentity['type'])
            ->where('shareable_id', $eventTargetIdentity['id'])
            ->whereIn('status', [ShareStatus::Created, ShareStatus::Shared])
            ->first();

        return [
            'is_following' => $this->stateResolver->isFollowing($actor, $eventTarget),
            'is_bookmarked' => $this->stateResolver->isBookmarked($actor, $eventTarget),
            'response' => $response?->response_type,
            'subscriptions' => $this->stateResolver->subscriptionsFor($actor, $eventTarget),
            'reminders' => $this->stateResolver->remindersFor($actor, $eventTarget),
            'share' => $share ? [
                'id' => $share->getKey(),
                'share_url' => $share->share_url,
                'share_token' => $share->share_token,
                'channel' => $share->channel,
                'status' => $share->status,
                'shared_at' => $share->shared_at,
            ] : null,
        ];
    }
}
