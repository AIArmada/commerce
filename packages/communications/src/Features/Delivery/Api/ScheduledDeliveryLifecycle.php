<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

use DateTimeInterface;

interface ScheduledDeliveryLifecycle
{
    public function queueDue(string $communicationId, DateTimeInterface $now): void;

    public function expire(string $communicationId): void;
}
