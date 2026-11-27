<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class LinkCustomerToUser
{
    public function execute(Customer $customer, Model $user): Customer
    {
        if (! $user->exists || $user->getKey() === null) {
            throw ValidationException::withMessages(['user_id' => 'A persisted user is required.']);
        }

        return $customer->getConnection()->transaction(function () use ($customer, $user): Customer {
            if (Customer::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
            }

            $locked = $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null && (string) $locked->user_id !== (string) $user->getKey()) {
                throw ValidationException::withMessages(['user_id' => 'Customer already belongs to another user.']);
            }

            $locked->forceFill(['user_id' => $user->getKey(), 'is_guest' => false]);

            if ($locked->isDirty()) {
                $locked->save();
            }

            return $locked;
        });
    }
}
