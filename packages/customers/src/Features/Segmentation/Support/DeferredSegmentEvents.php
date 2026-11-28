<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Features\Segmentation\Events\CustomerSegmentChanged;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;

final class DeferredSegmentEvents
{
    public static function dispatch(Customer $customer, Segment $segment, string $action): void
    {
        $connection = $customer->getConnection();
        $owner = $customer->owner_type !== null || $customer->owner_id !== null
            ? OwnerContext::fromTypeAndId($customer->owner_type, $customer->owner_id)
            : null;

        $connection->afterCommit(function () use ($customer, $segment, $action, $owner): void {
            if (Customer::ownerScopeConfig()->enabled) {
                OwnerContext::withOwner($owner, fn () => event(new CustomerSegmentChanged($customer, $segment, $action)));

                return;
            }

            event(new CustomerSegmentChanged($customer, $segment, $action));
        });
    }
}
