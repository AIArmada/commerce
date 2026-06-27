<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Integrations\Events;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Models\Share;
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
        return $this->engagementManager->share($actor, $eventTarget, $options);
    }

    public function stateFor(mixed $actor, mixed $eventTarget): array
    {
        $response = $this->stateResolver->responseFor($actor, $eventTarget);

        $share = Share::query()
            ->where('sharer_type', $actor->getMorphClass())
            ->where('sharer_id', $actor->getKey())
            ->where('shareable_type', $eventTarget->getMorphClass())
            ->where('shareable_id', $eventTarget->getKey())
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
