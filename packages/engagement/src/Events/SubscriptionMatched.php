<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

final readonly class SubscriptionMatched
{
    public function __construct(
        public string $subscriptionId,
        public string $subscriberType,
        public string $subscriberId,
        public ?string $subscribableType,
        public ?string $subscribableId,
        public ?string $ownerType,
        public ?string $ownerId,
        public string $subscriptionType,
        public string $subjectType,
        public string $subjectId,
        public string $trigger,
    ) {}
}
