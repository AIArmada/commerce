<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Models\EventChange;

interface EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventChange $notice, EventChangeNoticeAudienceData $audiences): void;
}
