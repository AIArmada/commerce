<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Policies\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

trait HandlesPricingOwnerScoping
{
    private function canAccess(Model $model): bool
    {
        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $this->isGlobalModel($model);
        }

        if ($this->belongsToOwner($model, $owner)) {
            return true;
        }

        return (bool) config('pricing.features.owner.include_global', false)
            && $this->isGlobalModel($model);
    }

    private function canMutate(Model $model): bool
    {
        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return true;
        }

        $owner = OwnerContext::resolve();

        return $owner === null
            ? OwnerContext::isExplicitGlobal() && $this->isGlobalModel($model)
            : $this->belongsToOwner($model, $owner);
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

        return $isGlobal && $model->getAttribute('owner_type') === null && $model->getAttribute('owner_id') === null;
    }
}
