<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Models\EventUpdate;
use Illuminate\Support\Collection;

final class DefaultEventChangeNoticeAudienceResolver implements EventChangeNoticeAudienceResolver
{
    public function resolve(EventUpdate $update, string $audienceScope): Collection
    {
        return new Collection;
    }
}
