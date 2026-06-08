<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventChangeNotice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EventChangeNoticePublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly EventChangeNotice $notice,
    ) {}
}
