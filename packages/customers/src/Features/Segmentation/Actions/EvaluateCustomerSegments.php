<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Features\Segmentation\Events\CustomerSegmentChanged;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class EvaluateCustomerSegments
{
    /**
     * Evaluate and update segment memberships for a single customer.
     *
     * @return Collection<int, Segment> Segments the customer now belongs to
     */
    public function execute(Customer $customer): Collection
    {
        if (! config('customers.features.segments.auto_assign', true)) {
            return collect();
        }

        if (! $customer->exists) {
            throw new InvalidArgumentException('Customer must be persisted.');
        }

        if (Customer::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Customer::class, $customer->getKey());
        }

        return $customer->getConnection()->transaction(function () use ($customer): Collection {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            return $this->evaluateLocked($locked);
        });
    }

    /** @return Collection<int, Segment> */
    private function evaluateLocked(Customer $customer): Collection
    {
        $customerOwner = OwnerContext::fromTypeAndId($customer->owner_type, $customer->owner_id);

        $automaticSegments = Segment::query()
            ->active()
            ->automatic()
            ->forOwner($customerOwner, includeGlobal: false)
            ->get();

        $matchingSegments = collect();

        foreach ($automaticSegments as $segment) {
            if ($this->customerMatchesSegment($customer, $segment)) {
                $matchingSegments->push($segment);
            }
        }

        // Get current automatic segments
        $currentAutoSegmentIds = $customer->segments()
            ->where('is_automatic', true)
            ->pluck('id')
            ->toArray();

        $newAutoSegmentIds = $matchingSegments->pluck('id')->toArray();

        $addedIds = array_values(array_diff($newAutoSegmentIds, $currentAutoSegmentIds));
        $removedIds = array_values(array_diff($currentAutoSegmentIds, $newAutoSegmentIds));

        if ($removedIds !== []) {
            $customer->segments()->detach($removedIds);
        }

        if ($addedIds !== []) {
            $customer->segments()->syncWithoutDetaching($addedIds);
        }

        if ($addedIds === [] && $removedIds === []) {
            return $matchingSegments;
        }

        $segmentsById = Segment::query()
            ->forOwner($customerOwner, includeGlobal: false)
            ->whereIn('id', array_values(array_unique(array_merge($addedIds, $removedIds))))
            ->get()
            ->keyBy('id');

        foreach ($addedIds as $segmentId) {
            /** @var Segment|null $segment */
            $segment = $segmentsById->get($segmentId);
            if ($segment !== null) {
                event(new CustomerSegmentChanged($customer, $segment, 'added'));
            }
        }

        foreach ($removedIds as $segmentId) {
            /** @var Segment|null $segment */
            $segment = $segmentsById->get($segmentId);
            if ($segment !== null) {
                event(new CustomerSegmentChanged($customer, $segment, 'removed'));
            }
        }

        return $matchingSegments;
    }

    /**
     * Check if a customer matches a segment's conditions.
     */
    public function customerMatchesSegment(Customer $customer, Segment $segment): bool
    {
        $conditions = $segment->conditions ?? [];

        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($customer, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against a customer.
     *
     * Conditions can use 'value_numeric', 'value_boolean', 'value_status' keys (Filament form)
     * or a single public API 'value' key. We normalize before matching.
     *
     * @param  array<string, mixed>  $condition
     */
    protected function evaluateCondition(Customer $customer, array $condition): bool
    {
        if (! $customer->isActive()) {
            return false;
        }

        $field = $condition['field'] ?? null;

        if (! is_string($field) || $field === '') {
            return false;
        }

        $value = $condition['value_numeric']
            ?? $condition['value_boolean']
            ?? $condition['value_status']
            ?? $condition['value']
            ?? null;

        if ($value === null) {
            return false;
        }

        return match ($field) {
            'accepts_marketing' => $customer->accepts_marketing === (bool) $value,
            'status' => $customer->status->value === ($value instanceof CustomerStatus ? $value->value : (string) $value),
            'created_days_ago' => $customer->created_at !== null && $customer->created_at->lte(CarbonImmutable::now()->subDays((int) $value)),
            default => false,
        };
    }
}
