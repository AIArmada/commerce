<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventChangeLog;

final class EventChangeNoticePublished
{
    public function __construct(
        public EventChangeLog $changeLog,
    ) {}
}
