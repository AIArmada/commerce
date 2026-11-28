<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class SyncManualSegmentMemberships
{
    /** @param list<string> $ids */
    public function forCustomer(Customer $customer, array $ids): void
    {
        $customer->getConnection()->transaction(function () use ($customer, $ids): void {
            $owner = $this->ownerFor($customer);
            $customer = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $allowed = Segment::query()->forOwner($owner, includeGlobal: false)
                ->manual()->whereKey($ids)->pluck('id')->all();

            if (array_diff($ids, $allowed) !== []) {
                throw new AuthorizationException('Only manual segments in the current owner scope may be assigned.');
            }

            $current = $customer->segments()->where('is_automatic', false)->pluck('id')->all();
            $removed = array_values(array_diff($current, $allowed));
            $added = array_values(array_diff($allowed, $current));

            if ($removed !== []) {
                $customer->segments()->detach($removed);
            }

            if ($added !== []) {
                $customer->segments()->syncWithoutDetaching($added);
            }
        });
    }

    /** @param list<string> $ids */
    public function forSegment(Segment $segment, array $ids): void
    {
        $segment->getConnection()->transaction(function () use ($segment, $ids): void {
            $owner = $this->ownerFor($segment);
            $segment = Segment::query()->whereKey($segment->getKey())->lockForUpdate()->firstOrFail();

            if ($segment->is_automatic) {
                throw new AuthorizationException('Automatic segment memberships cannot be edited manually.');
            }

            $allowed = Customer::query()->forOwner($owner, includeGlobal: false)
                ->whereKey($ids)->pluck('id')->all();

            if (array_diff($ids, $allowed) !== []) {
                throw new AuthorizationException('Only customers in the current owner scope may be assigned.');
            }

            $segment->customers()->sync($allowed);
        });
    }

    private function ownerFor(Customer | Segment $record): ?Model
    {
        if ($record::ownerScopeConfig()->enabled) {
            $record = OwnerWriteGuard::findOrFailForOwner($record::class, $record->getKey());
        }

        return OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);
    }
}
