<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Models\EventChangeNotice;

interface EventChangeNoticeAudienceResolver
{
    public function resolve(EventChangeNotice $notice): EventChangeNoticeAudienceData;
}
