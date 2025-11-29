<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Resolvers;

use AIArmada\Affiliates\Contracts\AffiliateOwnerResolver;
use Illuminate\Database\Eloquent\Model;

final class NullOwnerResolver implements AffiliateOwnerResolver
{
    public function resolveCurrentOwner(): ?Model
    {
        return null;
    }
}
