<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\Affiliation;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class AffiliationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $affiliation_type,
        public readonly string | null | Optional $institution_id,
        public readonly bool $is_primary,
        public readonly array $roles,
    ) {}

    public static function fromAffiliation(Affiliation $affiliation): self
    {
        $roles = $affiliation->relationLoaded('roles')
            ? AffiliationRoleData::collect($affiliation->roles)->toArray()
            : [];

        return new self(
            id: $affiliation->id,
            affiliation_type: $affiliation->affiliation_type->value,
            institution_id: $affiliation->institution_id,
            is_primary: $affiliation->is_primary,
            roles: $roles,
        );
    }
}
