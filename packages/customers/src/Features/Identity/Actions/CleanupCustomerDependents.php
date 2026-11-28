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
use RuntimeException;
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
            $customer->contactMethods()->eachById(static function (ContactMethod $contactMethod): void {
                if (! $contactMethod->delete()) {
                    throw new RuntimeException('Customer dependent deletion was cancelled.');
                }
            });
            $customer->socialProfiles()->eachById(static function (SocialProfile $socialProfile): void {
                if (! $socialProfile->delete()) {
                    throw new RuntimeException('Customer dependent deletion was cancelled.');
                }
            });
            $customer->media()->eachById(static function (Media $medium): void {
                if (! $medium->delete()) {
                    throw new RuntimeException('Customer dependent deletion was cancelled.');
                }
            });
        });
    }
}
