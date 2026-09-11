<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AttributeSetPolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    private function canAccessAttributeSet(AttributeSet $set): bool
    {
        return $this->canAccess($set);
    }

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }

    public function delete(mixed $user, AttributeSet $set): bool
    {
        return $this->canAccessAttributeSet($set);
    }
}
