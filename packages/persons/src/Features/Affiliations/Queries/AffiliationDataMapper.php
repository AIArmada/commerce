<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Queries;

use AIArmada\Persons\Data\AffiliationData;
use AIArmada\Persons\Data\AffiliationRoleData;
use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use AIArmada\Persons\Features\Affiliations\Models\AffiliationRole;

final readonly class AffiliationDataMapper
{
    public function fromAffiliation(Affiliation $affiliation): AffiliationData
    {
        $roles = $affiliation->relationLoaded('roles')
            ? $affiliation->roles->map(fn (AffiliationRole $role): array => $this->fromAffiliationRole($role)->toArray())->all()
            : [];

        return new AffiliationData(
            id: $affiliation->id,
            affiliation_type: $affiliation->affiliation_type->value,
            institution_id: $affiliation->institution_id,
            is_primary: $affiliation->is_primary,
            roles: $roles,
        );
    }

    public function fromAffiliationRole(AffiliationRole $role): AffiliationRoleData
    {
        return new AffiliationRoleData(
            id: $role->id,
            role_name: $role->role_name,
            department: $role->department,
            is_current: $role->is_current,
        );
    }
}
