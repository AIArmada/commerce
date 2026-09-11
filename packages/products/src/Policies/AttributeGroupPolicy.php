<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AttributeGroupPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessAttributeGroup(AttributeGroup $group): bool
    {
        return $this->canAccess($group);
    }

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, AttributeGroup $group): bool
    {
        return $this->canAccessAttributeGroup($group);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, AttributeGroup $group): bool
    {
        return $this->canAccessAttributeGroup($group);
    }

    public function delete(mixed $user, AttributeGroup $group): bool
    {
        return $this->canAccessAttributeGroup($group);
    }
}
