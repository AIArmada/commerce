<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Models\EventChangeNotice;

final class NullEventChangeNoticeNotificationDispatcher implements EventChangeNoticeNotificationDispatcher
{
    public function dispatch(EventChangeNotice $notice, EventChangeNoticeAudienceData $audiences): void
    {
        // Intentionally no-op: hosts bind their own dispatcher when they want delivery.
    }
}
