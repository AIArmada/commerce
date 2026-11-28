<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class SaveCustomerDetails
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Customer
    {
        $customer = new Customer;

        return $customer->getConnection()->transaction(fn (): Customer => $this->persist($customer, $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(Customer $customer, array $attributes): Customer
    {
        return $customer->getConnection()->transaction(function () use ($customer, $attributes): Customer {
            if (Customer::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
            }
            $locked = $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            return $this->persist($locked, $attributes);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function persist(Customer $customer, array $attributes): Customer
    {
        $customer->fill(array_intersect_key($attributes, array_flip(['first_name', 'last_name', 'company'])));

        if (array_key_exists('status', $attributes)) {
            $value = $attributes['status'];
            $status = $value instanceof CustomerStatus ? $value : (is_string($value) ? CustomerStatus::tryFrom($value) : null);

            if ($status === null) {
                throw ValidationException::withMessages(['status' => 'Invalid customer status.']);
            }

            $customer->status = $status;
        }

        if (array_key_exists('accepts_marketing', $attributes)) {
            $input = $attributes['accepts_marketing'];
            $value = is_bool($input) || is_int($input) || (is_string($input) && mb_trim($input) !== '')
                ? filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;

            if ($value === null) {
                throw ValidationException::withMessages(['accepts_marketing' => 'Invalid marketing preference.']);
            }

            if ($customer->accepts_marketing !== $value) {
                $customer->accepts_marketing = $value;

                if ($value) {
                    $customer->marketing_consented_at = CarbonImmutable::now();
                } else {
                    $customer->marketing_revoked_at = CarbonImmutable::now();
                }
            }
        }

        if (! $customer->save()) {
            throw new RuntimeException('Customer details save was cancelled.');
        }

        return $customer;
    }
}
