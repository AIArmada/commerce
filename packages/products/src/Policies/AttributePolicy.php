<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AttributePolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessAttribute(Attribute $attribute): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($attribute);
        }

        if ($this->belongsToOwner($attribute, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($attribute);
    }

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Attribute $attribute): bool
    {
        return $this->canAccessAttribute($attribute);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, Attribute $attribute): bool
    {
        return $this->canAccessAttribute($attribute);
    }

    public function delete(mixed $user, Attribute $attribute): bool
    {
        return $this->canAccessAttribute($attribute);
    }
}
