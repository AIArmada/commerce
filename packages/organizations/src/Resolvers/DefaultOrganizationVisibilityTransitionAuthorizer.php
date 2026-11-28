<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Authorization\OrganizationVisibilityTransitionAuthorizer;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;

final readonly class DefaultOrganizationVisibilityTransitionAuthorizer implements OrganizationVisibilityTransitionAuthorizer
{
    public function __construct(private OrganizationAuthorization $authorization) {}

    public function authorize(Model $actor, Organization $organization, OrganizationVisibility $target): void
    {
        $this->authorization->authorize($actor, $organization, 'organization.change-visibility');
    }
}
