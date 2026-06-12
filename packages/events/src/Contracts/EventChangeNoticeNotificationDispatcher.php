<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventNotificationBatch;

interface EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventNotificationBatch $batch): void;
}
