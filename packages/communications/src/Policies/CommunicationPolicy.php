<?php

declare(strict_types=1);

namespace AIArmada\Communications\Policies;

use AIArmada\CommerceSupport\Policies\EnforcesOwnerScope;
use AIArmada\Communications\Models\Communication;

final class CommunicationPolicy
{
    use EnforcesOwnerScope;

    public function viewAny(mixed $user): bool
    {
        return $user !== null && $this->ownerContextAllowsAccess(Communication::class);
    }

    public function view(mixed $user, Communication $communication): bool
    {
        return $user !== null && $this->isViewableInOwnerScope($communication);
    }

    public function create(mixed $user): bool
    {
        return $user !== null && $this->ownerContextAllowsAccess(Communication::class);
    }

    public function update(mixed $user, Communication $communication): bool
    {
        return false;
    }

    public function delete(mixed $user, Communication $communication): bool
    {
        return false;
    }

    public function cancel(mixed $user, Communication $communication): bool
    {
        return $user !== null && $this->isMutableInOwnerScope($communication);
    }

    public function retry(mixed $user, Communication $communication): bool
    {
        return $user !== null && $this->isMutableInOwnerScope($communication);
    }
}
