<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class OptionValuePolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, OptionValue $value): bool
    {
        return $this->canAccess($value);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, OptionValue $value): bool
    {
        return $this->canAccess($value);
    }

    public function delete(mixed $user, OptionValue $value): bool
    {
        return $this->canAccess($value);
    }
}
