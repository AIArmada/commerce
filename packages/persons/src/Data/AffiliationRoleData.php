<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\AffiliationRole;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class AffiliationRoleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $role_name,
        public readonly string | null | Optional $department,
        public readonly bool $is_current,
    ) {}

    public static function fromAffiliationRole(AffiliationRole $role): self
    {
        return new self(
            id: $role->id,
            role_name: $role->role_name,
            department: $role->department,
            is_current: $role->is_current,
        );
    }
}
