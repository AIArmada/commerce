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
            return $this->isGlobalModel($model);
        }

        if ($this->belongsToOwner($model, $owner)) {
            return true;
        }

        return (bool) config('pricing.features.owner.include_global', false)
            && $this->isGlobalModel($model);
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
}
