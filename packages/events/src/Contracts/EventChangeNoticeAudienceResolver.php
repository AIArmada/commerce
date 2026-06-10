<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Models\EventChange;

interface EventChangeNoticeAudienceResolver
{
    public function resolve(EventChange $notice): EventChangeNoticeAudienceData;
}
