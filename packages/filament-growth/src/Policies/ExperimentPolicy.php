<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ExperimentPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (OwnerUiScope::canCreate(Experiment::class)) {
            if (! TrackedProperty::ownerScopeConfig()->enabled) {
                return true;
            }

            return OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false)->exists();
        }

        if (Experiment::ownerScopeConfig()->enabled) {
            return false;
        }

        return Experiment::query()->exists();
    }

    public function view(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::canViewExperiment($experiment);
    }

    public function create(Authorizable $user): bool
    {
        if (Experiment::ownerScopeConfig()->enabled && ! OwnerUiScope::canCreate(Experiment::class)) {
            return false;
        }

        if (! TrackedProperty::ownerScopeConfig()->enabled) {
            return true;
        }

        return OwnerUiScope::apply(TrackedProperty::query(), includeGlobal: false)->exists();
    }

    public function update(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::scopeExperiments(Experiment::query(), writable: true)->whereKey($experiment->getKey())->exists();
    }

    public function delete(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::scopeExperiments(Experiment::query(), writable: true)->whereKey($experiment->getKey())->exists();
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    public function restore(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::scopeExperiments(Experiment::query(), writable: true)->whereKey($experiment->getKey())->exists();
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    public function forceDelete(Authorizable $user, Experiment $experiment): bool
    {
        return ExperimentAccess::scopeExperiments(Experiment::query(), writable: true)->whereKey($experiment->getKey())->exists();
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->hasWritableExperiments();
    }

    private function hasWritableExperiments(): bool
    {
        return OwnerUiScope::canCreate(Experiment::class)
            && ExperimentAccess::scopeExperiments(Experiment::query(), writable: true)->exists();
    }
}
