<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Queries;

use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;

final class DefaultEventChangeNoticeAudienceResolver implements EventChangeNoticeAudienceResolver
{
    public function resolve(string $updateId, string $audienceScope): iterable
    {
        return [];
    }
}
