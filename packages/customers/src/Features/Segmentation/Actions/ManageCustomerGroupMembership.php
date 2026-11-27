<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;

final readonly class ManageCustomerGroupMembership
{
    public function add(CustomerGroup $group, Customer $customer, string $role = 'member'): void
    {
        $this->mutate($group, $customer, static function (CustomerGroup $locked, Customer $member) use ($role): void {
            $locked->members()->syncWithoutDetaching([
                $member->getKey() => [
                    'role' => $role,
                    'joined_at' => CarbonImmutable::now(),
                ],
            ]);
        });
    }

    public function remove(CustomerGroup $group, Customer $customer): void
    {
        $this->mutate($group, $customer, static function (CustomerGroup $locked, Customer $member): void {
            $locked->members()->detach($member->getKey());
        });
    }

    public function promote(CustomerGroup $group, Customer $customer): void
    {
        $this->changeRole($group, $customer, 'admin');
    }

    public function demote(CustomerGroup $group, Customer $customer): void
    {
        $this->changeRole($group, $customer, 'member');
    }

    private function changeRole(CustomerGroup $group, Customer $customer, string $role): void
    {
        $this->mutate($group, $customer, static function (CustomerGroup $locked, Customer $member) use ($role): void {
            $locked->members()->updateExistingPivot($member->getKey(), ['role' => $role]);
        });
    }

    /** @param Closure(CustomerGroup, Customer): void $operation */
    private function mutate(CustomerGroup $group, Customer $customer, Closure $operation): void
    {
        if (! $group->exists || ! $customer->exists || $group->getKey() === null || $customer->getKey() === null) {
            throw new InvalidArgumentException('Group and customer must be persisted.');
        }

        if ($group->owner_type !== $customer->owner_type || $group->owner_id !== $customer->owner_id) {
            throw new InvalidArgumentException('Group and customer must share the same owner context.');
        }

        if (CustomerGroup::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerGroup::class, $group->getKey());
        }

        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
        }

        $group->getConnection()->transaction(function () use ($group, $customer, $operation): void {
            $member = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $locked = CustomerGroup::query()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->owner_type !== $member->owner_type || $locked->owner_id !== $member->owner_id) {
                throw new InvalidArgumentException('Group and customer must share the same owner context.');
            }

            $operation($locked, $member);
        });
    }
}
