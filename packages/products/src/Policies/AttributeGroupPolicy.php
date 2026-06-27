<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AttributeGroupPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessAttributeGroup(AttributeGroup $group): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($group);
        }

        if ($this->belongsToOwner($group, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($group);
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
