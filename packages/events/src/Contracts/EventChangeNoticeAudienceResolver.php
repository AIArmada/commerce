<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventUpdate;

interface EventChangeNoticeAudienceResolver
{
    public function resolve(EventUpdate $update, string $audienceScope): iterable;
}
