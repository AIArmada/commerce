<?php

declare(strict_types=1);

namespace AIArmada\Communications\Policies;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Communications\Models\Communication;
use Illuminate\Foundation\Auth\User;

final class CommunicationPolicy
{
    public function __construct(
        private readonly OwnerResolverInterface $ownerResolver,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Communication $communication): bool
    {
        return $this->ownerResolver->resolve() === null
            || $communication->owner_id === $this->ownerResolver->resolve()?->getKey();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function cancel(User $user, Communication $communication): bool
    {
        return $this->view($user, $communication);
    }

    public function retry(User $user, Communication $communication): bool
    {
        return $this->view($user, $communication);
    }
}
