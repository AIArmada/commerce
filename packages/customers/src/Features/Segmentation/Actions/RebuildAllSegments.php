<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Features\Segmentation\Events\CustomerSegmentChanged;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class RebuildAllSegments
{
    /**
     * Rebuild automatic segments for a specific owner.
     *
     * @return array<string, int> Map of segment names to customer counts
     */
    public function forOwner(?Model $owner = null): array
    {
        $segments = Segment::query()
            ->active()
            ->automatic()
            ->forOwner($owner, includeGlobal: false)
            ->get();

        $results = [];

        foreach ($segments as $segment) {
            $results[$segment->name] = OwnerContext::withOwner($owner, fn (): int => $this->rebuildSegment($segment));
        }

        return $results;
    }

    /**
     * Rebuild automatic segments for every distinct owner (and global).
     *
     * @return array<string, array<string, int>> Map of owner labels to segment counts
     */
    public function forAllOwners(): array
    {
        $columns = OwnerTupleColumns::forModelClass(Segment::class);

        $owners = Segment::query()
            ->withoutOwnerScope()
            ->active()
            ->automatic()
            ->select([
                $columns->ownerTypeColumn . ' as owner_type',
                $columns->ownerIdColumn . ' as owner_id',
            ])
            ->distinct()
            ->get();

        $results = [];

        foreach ($owners as $row) {
            $tuple = OwnerTupleParser::fromRow($row, new OwnerTupleColumns);
            $owner = $tuple->toOwnerModel();

            $label = $this->ownerLabel($tuple->owner_type, $tuple->owner_id);

            $results[$label] = OwnerContext::withOwner($owner, fn (): array => $this->forOwner($owner));
        }

        return $results;
    }

    /**
     * Rebuild a single segment's customer list.
     */
    public function rebuildSegment(Segment $segment): int
    {
        if (Segment::ownerScopeConfig()->enabled) {
            $segment = OwnerWriteGuard::findOrFailForOwner(Segment::class, $segment->getKey());
        }

        $segmentOwner = OwnerContext::fromTypeAndId($segment->owner_type, $segment->owner_id);

        return OwnerContext::withOwner($segmentOwner, fn (): int => $segment->getConnection()->transaction(function () use ($segment, $segmentOwner): int {
            $segment = Segment::query()->whereKey($segment->getKey())->lockForUpdate()->firstOrFail();

            if (! $segment->is_automatic || ! $segment->isActive()) {
                return $segment->customers()->count();
            }

            $connection = $segment->getConnection();
            $pivotTable = config('customers.database.tables.segment_customer', 'customer_segment_customer');
            $matchingQuery = $segment->matchingCustomersQuery()->select('id');
            $total = (clone $matchingQuery)->count();
            $now = CarbonImmutable::now();

            $matchingQuery
                ->whereNotIn('id', function ($query) use ($pivotTable, $segment): void {
                    $query->select('customer_id')
                        ->from($pivotTable)
                        ->where('segment_id', $segment->getKey());
                })
                ->chunkById(200, function (Collection $customers) use ($connection, $pivotTable, $segment, $segmentOwner, $now): void {
                    $ids = array_map(static fn (Customer $customer): string => (string) $customer->getKey(), $customers->all());

                    if ($ids === []) {
                        return;
                    }

                    $rows = array_map(static fn (string $id): array => [
                        'segment_id' => $segment->getKey(),
                        'customer_id' => $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $ids);

                    $connection->table($pivotTable)->insertOrIgnore($rows);

                    $this->deferMembershipEvents($segment, $segmentOwner, $ids, 'added');
                });

            Customer::query()
                ->forOwner($segmentOwner, includeGlobal: false)
                ->whereIn('id', function ($query) use ($pivotTable, $segment): void {
                    $query->select('customer_id')
                        ->from($pivotTable)
                        ->where('segment_id', $segment->getKey());
                })
                ->whereNotIn('id', $segment->matchingCustomersQuery()->select('id'))
                ->select('id')
                ->chunkById(200, function (Collection $customers) use ($connection, $pivotTable, $segment, $segmentOwner): void {
                    $ids = array_map(static fn (Customer $customer): string => (string) $customer->getKey(), $customers->all());

                    if ($ids === []) {
                        return;
                    }

                    $connection->table($pivotTable)
                        ->where('segment_id', $segment->getKey())
                        ->whereIn('customer_id', $ids)
                        ->delete();

                    $this->deferMembershipEvents($segment, $segmentOwner, $ids, 'removed');
                });

            return $total;
        }));
    }

    /**
     * @param  array<int, string>  $customerIds
     */
    private function deferMembershipEvents(Segment $segment, ?Model $segmentOwner, array $customerIds, string $action): void
    {
        $segment->getConnection()->afterCommit(function () use ($segment, $segmentOwner, $customerIds, $action): void {
            OwnerContext::withOwner($segmentOwner, function () use ($segment, $segmentOwner, $customerIds, $action): void {
                $customers = Customer::query()
                    ->forOwner($segmentOwner, includeGlobal: false)
                    ->whereKey($customerIds)
                    ->orderBy('id')
                    ->get();

                foreach ($customers as $customer) {
                    event(new CustomerSegmentChanged($customer, $segment, $action));
                }
            });
        });
    }

    private function ownerLabel(?string $ownerType, string | int | null $ownerId): string
    {
        if ($ownerType === null || $ownerId === null) {
            return 'global';
        }

        return "{$ownerType}:{$ownerId}";
    }
}
