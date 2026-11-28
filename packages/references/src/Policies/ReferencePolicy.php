<?php

declare(strict_types=1);

namespace AIArmada\References\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ReferencePolicy
{
    use HandlesAuthorization;

    public function viewAny(mixed $user): bool
    {
        $config = Reference::ownerScopeConfig();

        return ! $config->enabled
            || OwnerContext::resolveConfigured($config) !== null
            || OwnerContext::isExplicitGlobal();
    }

    public function view(mixed $user, Reference $reference): bool
    {
        $config = Reference::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $reference->isGlobal();
        }

        return $reference->belongsToOwner($owner)
            || ($config->includeGlobal && $reference->isGlobal());
    }

    public function create(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(mixed $user, Reference $reference): bool
    {
        return $this->canWriteReference($reference);
    }

    public function updateAny(mixed $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(mixed $user, Reference $reference): bool
    {
        return $this->canWriteReference($reference);
    }

    public function duplicate(mixed $user, Reference $reference): bool
    {
        return $this->view($user, $reference);
    }

    private function canWriteReference(Reference $reference): bool
    {
        $config = Reference::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        if ($reference->isGlobal()) {
            return OwnerContext::isExplicitGlobal();
        }

        $owner = OwnerContext::resolveConfigured($config);

        return $owner !== null && $reference->belongsToOwner($owner);
    }
}
