<?php

declare(strict_types=1);

namespace AIArmada\Products\Policies;

use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Policies\Concerns\HandlesOwnerScoping;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AttributeValuePolicy
{
    use HandlesAuthorization;
    use HandlesOwnerScoping;

    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, AttributeValue $value): bool
    {
        return $this->canAccess($value);
    }

    public function create(mixed $user): bool
    {
        return true;
    }

    public function update(mixed $user, AttributeValue $value): bool
    {
        return $this->canAccess($value);
    }

    public function delete(mixed $user, AttributeValue $value): bool
    {
        return $this->canAccess($value);
    }
}
