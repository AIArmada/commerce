<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Contracts;

use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;

interface OrganizationAuthorization
{
    public function authorize(Model $actor, Organization $organization, string $ability): void;
}
