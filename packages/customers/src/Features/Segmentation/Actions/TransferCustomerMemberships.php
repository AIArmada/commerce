<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Segmentation\Actions;

use AIArmada\Customers\Features\Segmentation\Api\CustomerMembershipTransfer;
use AIArmada\Customers\Models\Customer;
use InvalidArgumentException;

final readonly class TransferCustomerMemberships implements CustomerMembershipTransfer
{
    public function __construct(
        private TransferSegmentMemberships $segments,
        private TransferCustomerGroupMemberships $groups,
    ) {}

    public function transfer(string $sourceId, string $targetId): void
    {
        if ($sourceId === $targetId) {
            throw new InvalidArgumentException('Customers must be distinct persisted records.');
        }

        (new Customer)->getConnection()->transaction(function () use ($sourceId, $targetId): void {
            $locked = Customer::query()
                ->whereKey([$sourceId, $targetId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $source = $locked->find($sourceId);
            $target = $locked->find($targetId);

            if (! $source instanceof Customer || ! $target instanceof Customer) {
                throw new InvalidArgumentException('Both customers must remain accessible during the transfer.');
            }

            $this->segments->execute($source, $target);
            $this->groups->execute($source, $target);
        });
    }
}
