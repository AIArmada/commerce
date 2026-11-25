<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventChangeNoticeNotificationData;

interface EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventChangeNoticeNotificationData $notice): void;
}
