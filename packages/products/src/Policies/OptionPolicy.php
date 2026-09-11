<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Option;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class OptionPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Option $option): bool
    {
        return $this->canAccess($option);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, Option $option): bool
    {
        return $this->canAccess($option);
    }

    public function delete(mixed $user, Option $option): bool
    {
        return $this->canAccess($option);
    }
}
