<?php

declare(strict_types=1);

namespace AIArmada\Customers\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class CustomerPolicy
{
    use HandlesAuthorization;

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    private function isAccessible(Customer $customer): bool
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return true;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('customers.features.owner.include_global', false);

        if ($owner === null) {
            return $customer->owner_type === null && $customer->owner_id === null;
        }

        if ($includeGlobal && method_exists($customer, 'isGlobal') && $customer->isGlobal()) {
            return true;
        }

        if (method_exists($customer, 'belongsToOwner')) {
            return $customer->belongsToOwner($owner);
        }

        return $customer->owner_type === $owner->getMorphClass()
            && $customer->owner_id === $owner->getKey();
    }

    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Customer $customer): bool
    {
        if ($this->isAccessible($customer)) {
            return true;
        }

        return $customer->user_id !== null && $customer->user_id === $user->id;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Customer $customer): bool
    {
        return $this->isAccessible($customer);
    }

    public function delete($user, Customer $customer): bool
    {
        // Cannot delete customers with orders
        // This would integrate with orders package
        return $this->isAccessible($customer);
    }

    /**
     * Determine if user can add credit to customer wallet.
     */
    public function addCredit($user, Customer $customer): bool
    {
        return $this->isAccessible($customer);
    }

    /**
     * Determine if user can deduct credit from customer wallet.
     */
    public function deductCredit($user, Customer $customer): bool
    {
        return $this->isAccessible($customer);
    }
}
