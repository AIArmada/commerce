<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Identity\Models\CustomerNote;
use AIArmada\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final readonly class ManageCustomerNote
{
    /** @param array<string, mixed> $attributes */
    public function create(Customer $customer, array $attributes, string | int | null $authorId = null): CustomerNote
    {
        return $customer->getConnection()->transaction(function () use ($customer, $attributes, $authorId): CustomerNote {
            $locked = $this->lockCustomer($customer);
            $data = $this->attributes($attributes);
            $data['created_by'] = $authorId;

            $note = $locked->notes()->make($data);

            if (! $note->save()) {
                throw new RuntimeException('Customer note creation was cancelled.');
            }

            return $note;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Customer $customer, CustomerNote $note, array $attributes): CustomerNote
    {
        return $customer->getConnection()->transaction(function () use ($customer, $note, $attributes): CustomerNote {
            $locked = $this->lockNote($this->lockCustomer($customer), $note);
            $locked->fill($this->attributes($attributes));

            if (! $locked->save()) {
                throw new RuntimeException('Customer note update was cancelled.');
            }

            return $locked;
        });
    }

    public function delete(Customer $customer, CustomerNote $note): bool
    {
        return $customer->getConnection()->transaction(function () use ($customer, $note): bool {
            if (! $this->lockNote($this->lockCustomer($customer), $note)->delete()) {
                throw new RuntimeException('Customer note deletion was cancelled.');
            }

            return true;
        });
    }

    private function lockCustomer(Customer $customer): Customer
    {
        if (! $customer->exists || $customer->getKey() === null) {
            throw new InvalidArgumentException('Customer must be persisted.');
        }

        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
        }

        return Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockNote(Customer $customer, CustomerNote $note): CustomerNote
    {
        if (! $note->exists || $note->getKey() === null) {
            throw new InvalidArgumentException('Note must be persisted.');
        }

        if (CustomerNote::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(CustomerNote::class, $note->getKey());
        }

        $locked = $customer->notes()->whereKey($note->getKey())->lockForUpdate()->first();

        if ($locked === null || $locked->owner_type !== $customer->owner_type || $locked->owner_id !== $customer->owner_id) {
            throw new AuthorizationException('Note does not belong to this customer and owner.');
        }

        return $locked;
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        $data = array_intersect_key($attributes, array_flip(['content', 'is_internal', 'is_pinned', 'metadata']));

        if (array_key_exists('content', $data) && (! is_string($data['content']) || mb_trim($data['content']) === '')) {
            throw ValidationException::withMessages(['content' => 'Note content must be a non-empty string.']);
        }

        return $data;
    }
}
