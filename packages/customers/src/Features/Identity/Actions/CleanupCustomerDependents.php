<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Customers\Features\Identity\Api\CustomerDependentsCleaner;
use AIArmada\Customers\Features\Segmentation\Api\CustomerMembershipCleaner;
use AIArmada\Customers\Models\Customer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class CleanupCustomerDependents implements CustomerDependentsCleaner
{
    public function __construct(
        private AddressAttachmentWriter $addresses,
        private CustomerMembershipCleaner $memberships,
    ) {}

    public function cleanup(string $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);

        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customerId);
        }

        $customer->getConnection()->transaction(function () use ($customer, $customerId): void {
            $this->addresses->detachAll($customer->getMorphClass(), $customerId);
            $customer->notes()->delete();
            $this->memberships->cleanup($customerId);
            $customer->contactMethods()->eachById(fn (ContactMethod $contactMethod): bool => $contactMethod->delete());
            $customer->socialProfiles()->eachById(fn (SocialProfile $socialProfile): bool => $socialProfile->delete());
            $customer->media()->eachById(fn (Media $medium): bool => (bool) $medium->delete());
        });
    }
}
