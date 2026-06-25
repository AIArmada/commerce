<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Events\Events\EventPublished;

final class MatchSubscriptionsOnEventOccurrencePublished
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
    ) {}

    public function handle(EventPublished $event): void
    {
        $eventModel = $event->event;
        $owner = OwnerTupleParser::fromTypeAndId(
            $eventModel->owner_type,
            $eventModel->owner_id,
        )->toOwnerModel();

        OwnerContext::withOwner($owner, function () use ($eventModel): void {
            $criteria = [
                'subject_type' => 'event',
                'event_id' => $eventModel->getKey(),
                'delivery_mode' => $eventModel->delivery_mode,
                'status' => $eventModel->status,
                'visibility' => $eventModel->visibility,
            ];

            foreach ($this->subscriptionManager->matchingSubscriptions($eventModel, 'event_published', $criteria) as $subscription) {
            }
        });
    }
}
