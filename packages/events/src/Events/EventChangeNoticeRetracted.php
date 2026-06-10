<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventChange;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EventChangeNoticeRetracted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly EventChange $notice,
    ) {}
}
