<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventChangeLog;

interface EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventChangeLog $changeLog): void;
}
