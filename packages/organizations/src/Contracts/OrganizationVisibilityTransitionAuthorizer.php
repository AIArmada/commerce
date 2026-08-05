<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;

interface OrganizationVisibilityTransitionAuthorizer
{
    public function authorize(Model $actor, Organization $organization, OrganizationVisibility $target): void;
}
