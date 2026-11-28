<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class LinkCustomerToPerson
{
    public function executeByKey(Customer $customer, string $personId): Customer
    {
        $personClass = $this->configuredPersonClass();
        $person = $personClass::query()->whereKey($personId)->firstOrFail();

        return $this->execute($customer, $person);
    }

    public function execute(Customer $customer, Model $person): Customer
    {
        $personClass = $this->configuredPersonClass();

        if (! $person instanceof $personClass || ! $person->exists || $person->getKey() === null) {
            throw new InvalidArgumentException('The linked model must be a persisted configured person model.');
        }

        return $customer->getConnection()->transaction(function () use ($customer, $person): Customer {
            if (Customer::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
            }

            $ownedCustomer = $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $ownedCustomer->forceFill(['person_id' => $person->getKey()]);

            if ($ownedCustomer->isDirty() && ! $ownedCustomer->save()) {
                throw new RuntimeException('Customer person link was cancelled.');
            }

            return $ownedCustomer->refresh();
        });
    }

    /**
     * @return class-string<Model>
     */
    private function configuredPersonClass(): string
    {
        $personClass = config('persons.models.person');

        if (! is_string($personClass) || ! class_exists($personClass) || ! is_a($personClass, Model::class, true)) {
            throw new LogicException('Configure persons.models.person with an Eloquent model before linking a customer.');
        }

        return $personClass;
    }
}
