<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Services;

use AIArmada\Customers\Features\Segmentation\Actions\AssignCustomerToSegment;
use AIArmada\Customers\Features\Segmentation\Actions\EvaluateCustomerSegments;
use AIArmada\Customers\Features\Segmentation\Actions\RebuildAllSegments;
use AIArmada\Customers\Features\Segmentation\Actions\RemoveCustomerFromSegment;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Service for managing customer segmentation.
 *
 * Provides methods for:
 * - Automatically segmenting customers based on rules
 * - Manually managing segment membership
 * - Bulk rebuilding segment memberships
 */
final class SegmentationService
{
    public function __construct(
        private readonly AssignCustomerToSegment $assignCustomerToSegment,
        private readonly RemoveCustomerFromSegment $removeCustomerFromSegment,
        private readonly RebuildAllSegments $rebuildAllSegmentsAction,
    ) {}

    /**
     * Rebuild all automatic segments.
     *
     * @return array<string, int> Map of segment names to customer counts
     */
    public function rebuildAllSegments(?Model $owner = null): array
    {
        return $this->rebuildAllSegmentsAction->forOwner($owner);
    }

    /**
     * Rebuild a single segment's customer list.
     */
    public function rebuildSegment(Segment $segment): int
    {
        return $this->rebuildAllSegmentsAction->rebuildSegment($segment);
    }

    /** @return Collection<int, Segment> */
    public function evaluateCustomer(Customer $customer): Collection
    {
        return app(EvaluateCustomerSegments::class)->execute($customer);
    }

    public function customerMatchesSegment(Customer $customer, Segment $segment): bool
    {
        return app(EvaluateCustomerSegments::class)->customerMatchesSegment($customer, $segment);
    }

    /**
     * Add a customer to a manual segment.
     */
    public function addToSegment(Customer $customer, Segment $segment): bool
    {
        if (! $this->customerAndSegmentShareOwner($customer, $segment)) {
            return false;
        }

        if ($customer->segments()->whereKey($segment->id)->exists()) {
            return false;
        }

        try {
            $this->assignCustomerToSegment->execute($customer, $segment);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Remove a customer from a segment.
     */
    public function removeFromSegment(Customer $customer, Segment $segment): bool
    {
        if (! $this->customerAndSegmentShareOwner($customer, $segment)) {
            return false;
        }

        if (! $customer->segments()->whereKey($segment->id)->exists()) {
            return false;
        }

        try {
            $this->removeCustomerFromSegment->execute($customer, $segment);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get segment statistics.
     *
     * @return array{customer_count: int, active_count: int, marketing_opted_in: int, marketing_opted_in_percentage: float}
     */
    public function getSegmentStats(Segment $segment): array
    {
        $customerCount = $segment->customers()->count();

        if ($customerCount === 0) {
            return [
                'customer_count' => 0,
                'active_count' => 0,
                'marketing_opted_in' => 0,
                'marketing_opted_in_percentage' => 0.0,
            ];
        }

        $activeCount = $segment->customers()->where('status', CustomerStatus::Active)->count();
        $marketingOptedIn = $segment->customers()->where('accepts_marketing', true)->count();

        return [
            'customer_count' => $customerCount,
            'active_count' => $activeCount,
            'marketing_opted_in' => $marketingOptedIn,
            'marketing_opted_in_percentage' => round(($marketingOptedIn / $customerCount) * 100, 1),
        ];
    }

    private function customerAndSegmentShareOwner(Customer $customer, Segment $segment): bool
    {
        if ($segment->owner_type === null && $segment->owner_id === null) {
            return $customer->owner_type === null && $customer->owner_id === null;
        }

        return $customer->owner_type === $segment->owner_type
            && $customer->owner_id === $segment->owner_id;
    }
}
