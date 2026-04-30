<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

final class AttributeGroupPolicy
{
    use HandlesAuthorization;

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

    private function belongsToOwner(Model $model, Model $owner): bool
    {
        if (! method_exists($model, 'belongsToOwner')) {
            return false;
        }

        /** @var bool $belongs */
        $belongs = $model->belongsToOwner($owner);

        return $belongs;
    }

    private function isGlobalModel(Model $model): bool
    {
        if (! method_exists($model, 'isGlobal')) {
            return false;
        }

        /** @var bool $isGlobal */
        $isGlobal = $model->isGlobal();

        return $isGlobal;
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
