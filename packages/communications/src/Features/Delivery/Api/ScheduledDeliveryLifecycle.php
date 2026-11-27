<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

use DateTimeInterface;

interface ScheduledDeliveryLifecycle
{
    public function queueDue(string $communicationId, DateTimeInterface $now): void;

    public function startQueued(string $communicationId): int;

    public function cancel(string $communicationId, DateTimeInterface $now): void;

    public function expire(string $communicationId): void;
}
