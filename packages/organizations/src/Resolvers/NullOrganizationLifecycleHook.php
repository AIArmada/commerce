<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;

final class NullOrganizationLifecycleHook implements OrganizationLifecycleHook
{
    public function created(Organization $organization, Model $actor): void {}

    public function visibilityChanged(Organization $organization, OrganizationVisibility $from, OrganizationVisibility $to, Model $actor): void {}

    public function statusChanged(Organization $organization, OrganizationStatus $from, OrganizationStatus $to, Model $actor): void {}

    public function ownershipTransferred(Organization $organization, Model $from, Model $to, Model $actor): void {}
}
