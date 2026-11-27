<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use InvalidArgumentException;

final class RemoveCustomerFromSegment
{
    public function execute(Customer $customer, Segment $segment): void
    {
        if (! $customer->exists || ! $segment->exists) {
            throw new InvalidArgumentException('Customer and segment must be persisted.');
        }

        if (! $this->shareOwner($customer, $segment)) {
            throw new InvalidArgumentException('Customer and segment must share the same owner context.');
        }

        if (! $customer->segments()->whereKey($segment->id)->exists()) {
            return;
        }

        $customer->segments()->detach($segment->id);
        event(new CustomerSegmentChanged($customer, $segment, 'removed'));
    }

    private function shareOwner(Customer $customer, Segment $segment): bool
    {
        if ($segment->owner_type === null && $segment->owner_id === null) {
            return $customer->owner_type === null && $customer->owner_id === null;
        }

        return $customer->owner_type === $segment->owner_type
            && $customer->owner_id === $segment->owner_id;
    }
}
