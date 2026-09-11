<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\Variant;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class VariantPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Variant $variant): bool
    {
        return $this->canAccess($variant);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, Variant $variant): bool
    {
        return $this->canAccess($variant);
    }

    public function delete(mixed $user, Variant $variant): bool
    {
        return $this->canAccess($variant);
    }
}
