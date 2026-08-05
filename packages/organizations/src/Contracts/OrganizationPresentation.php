<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\Organizations\Models\Organization;

interface OrganizationPresentation
{
    public function displayName(Organization $organization): string;

    public function profileUrl(Organization $organization): ?string;
}
