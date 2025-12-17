<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Models\Segment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class SegmentPolicy
{
    use HandlesAuthorization;

    private function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        return $resolver->resolve();
    }

    private function isAccessible(Segment $segment): bool
    {
        $owner = $this->resolveOwner();

        if ($owner === null) {
            return $segment->owner_type === null && $segment->owner_id === null;
        }

        if (method_exists($segment, 'isGlobal') && $segment->isGlobal()) {
            return true;
        }

        if (method_exists($segment, 'belongsToOwner')) {
            return $segment->belongsToOwner($owner);
        }

        return $segment->owner_type === $owner->getMorphClass()
            && $segment->owner_id === $owner->getKey();
    }

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Segment $segment): bool
    {
        return $this->isAccessible($segment);
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Segment $segment): bool
    {
        return $this->isAccessible($segment);
    }

    public function delete($user, Segment $segment): bool
    {
        return $this->isAccessible($segment);
    }

    /**
     * Determine if user can rebuild segment.
     */
    public function rebuild($user, Segment $segment): bool
    {
        return $this->update($user, $segment) && $segment->is_automatic;
    }
}
