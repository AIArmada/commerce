<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Collection;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

final class CollectionPolicy
{
    use HandlesAuthorization;

    private function canAccessCollection(Collection $collection): bool
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return $this->isGlobalModel($collection);
        }

        if ($this->belongsToOwner($collection, $owner)) {
            return true;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        return $includeGlobal && $this->isGlobalModel($collection);
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

    public function view(mixed $user, Collection $collection): bool
    {
        return $this->canAccessCollection($collection);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, Collection $collection): bool
    {
        return $this->canAccessCollection($collection);
    }

    public function delete(mixed $user, Collection $collection): bool
    {
        return $this->canAccessCollection($collection);
    }
}
