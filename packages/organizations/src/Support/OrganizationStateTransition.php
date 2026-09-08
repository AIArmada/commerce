<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Support;

use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Carbon\CarbonImmutable;

final class OrganizationStateTransition
{
    public function status(Organization $organization, OrganizationStatus $status, CarbonImmutable $at): void
    {
        $organization->transitionToStatus($status, $at);
    }

    public function visibility(Organization $organization, OrganizationVisibility $visibility, CarbonImmutable $at): void
    {
        $organization->transitionToVisibility($visibility, $at);
    }
}
