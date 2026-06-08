<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Models\EventChangeNotice;

interface EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventChangeNotice $notice, EventChangeNoticeAudienceData $audiences): void;
}
