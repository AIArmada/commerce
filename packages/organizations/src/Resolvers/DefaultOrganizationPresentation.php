<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Organizations\Contracts\OrganizationPresentation;
use AIArmada\Organizations\Models\Organization;

final class DefaultOrganizationPresentation implements OrganizationPresentation
{
    public function displayName(Organization $organization): string
    {
        return $organization->name;
    }

    public function profileUrl(Organization $organization): ?string
    {
        return null;
    }
}
