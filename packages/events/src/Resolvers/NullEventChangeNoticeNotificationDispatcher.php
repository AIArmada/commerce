<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Models\EventNotificationBatch;

final class NullEventChangeNoticeNotificationDispatcher implements EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventNotificationBatch $batch): void
    {
    }
}
