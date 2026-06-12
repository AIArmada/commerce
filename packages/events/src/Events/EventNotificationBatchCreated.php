<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventNotificationBatch;

final class EventNotificationBatchCreated
{
    public function __construct(
        public EventNotificationBatch $batch,
    ) {}
}
