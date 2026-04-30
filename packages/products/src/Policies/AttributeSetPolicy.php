<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeSet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

final class AttributeSetPolicy
{
    use HandlesAuthorization;

    private function canAccessAttributeSet(AttributeSet $set): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($set);
        }

        if ($this->belongsToOwner($set, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($set);
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

    public function view(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }

    public function delete(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }
}
