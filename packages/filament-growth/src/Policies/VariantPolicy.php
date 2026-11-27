<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Queries\ExperimentOwnership;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class VariantPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (OwnerUiScope::canCreate(Variant::class)) {
            if (! Experiment::ownerScopeConfig()->enabled && ! TrackedProperty::ownerScopeConfig()->enabled) {
                return true;
            }

            $experimentQuery = Experiment::ownerScopeConfig()->enabled
                ? OwnerUiScope::apply(Experiment::query(), includeGlobal: false)
                : OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false);

            if ($experimentQuery->exists()) {
                return true;
            }
        }

        if (Variant::ownerScopeConfig()->enabled) {
            return false;
        }

        return Variant::query()->exists();
    }

    public function view(Authorizable $user, Variant $variant): bool
    {
        return ExperimentAccess::canViewVariant($variant);
    }

    public function create(Authorizable $user): bool
    {
        return ExperimentAccess::canCreateVariant();
    }

    public function update(Authorizable $user, Variant $variant): bool
    {
        return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->whereKey($variant->getKey())->exists();
    }

    public function delete(Authorizable $user, Variant $variant): bool
    {
        return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->whereKey($variant->getKey())->exists();
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function restore(Authorizable $user, Variant $variant): bool
    {
        return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->whereKey($variant->getKey())->exists();
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    public function forceDelete(Authorizable $user, Variant $variant): bool
    {
        return ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->whereKey($variant->getKey())->exists();
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->hasWritableVariants();
    }

    private function hasWritableVariants(): bool
    {
        return OwnerUiScope::canCreate(Variant::class)
            && ExperimentOwnership::scopeVariants(Variant::query(), writable: true)->exists();
    }
}
