<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;

final class DetachDeletedSegmentMemberships
{
    public function forCustomer(Customer $customer): void
    {
        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
        }

        $customer->segments()->detach();
    }

    public function forSegment(Segment $segment): void
    {
        if (Segment::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Segment::class, $segment->getKey());
        }

        $segment->customers()->detach();
    }
}
