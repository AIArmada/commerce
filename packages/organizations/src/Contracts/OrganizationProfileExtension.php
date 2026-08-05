<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\Organizations\Models\Organization;

interface OrganizationProfileExtension
{
    /**
     * @return array<string, mixed>
     */
    public function attributes(Organization $organization): array;
}
