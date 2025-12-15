<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\Customers\Models\Segment;
use Illuminate\Auth\Access\HandlesAuthorization;

class SegmentPolicy
{
    use HandlesAuthorization;

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Segment $segment): bool
    {
        if (method_exists($segment, 'isOwnedBy')) {
            return $segment->isOwnedBy($user);
        }

        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Segment $segment): bool
    {
        if (method_exists($segment, 'isOwnedBy')) {
            return $segment->isOwnedBy($user);
        }

        return true;
    }

    public function delete($user, Segment $segment): bool
    {
        if (method_exists($segment, 'isOwnedBy')) {
            return $segment->isOwnedBy($user);
        }

        return true;
    }

    /**
     * Determine if user can rebuild segment.
     */
    public function rebuild($user, Segment $segment): bool
    {
        return $this->update($user, $segment) && $segment->is_automatic;
    }
}
