<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Organizations\Contracts\CurrentOrganizationResolver;
use AIArmada\Organizations\Models\Organization;

final class NullCurrentOrganizationResolver implements CurrentOrganizationResolver
{
    public function resolve(): ?Organization
    {
        return null;
    }
}
