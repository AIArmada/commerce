<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutReservationReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReservationRecord;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use LogicException;

final readonly class ReadPayoutReservation implements PayoutReservationReader
{
    public function assertSharedTransaction(int $connectionId): void
    {
        $connection = (new AffiliatePayoutOperation)->getConnection();

        if (spl_object_id($connection) !== $connectionId || $connection->transactionLevel() < 1) {
            throw new LogicException('Payout reservation requires a shared active database transaction.');
        }
    }

    public function affiliateIdForOperation(string $operationId, ?string $ownerType, ?string $ownerId, int $connectionId): ?string
    {
        $this->assertConnection($connectionId);

        $query = AffiliatePayoutOperation::query()->withoutOwnerScope()->whereKey($operationId);
        if ((bool) config('affiliates.owner.enabled', false)) {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        $id = $query->value('affiliate_id');

        return is_string($id) ? $id : null;
    }

    public function hasOperations(string $payoutId, int $connectionId): bool
    {
        $this->assertConnection($connectionId);

        return AffiliatePayoutOperation::query()->withoutOwnerScope()->where('affiliate_payout_id', $payoutId)->exists();
    }

    private function assertConnection(int $connectionId): void
    {
        if (spl_object_id((new AffiliatePayoutOperation)->getConnection()) !== $connectionId) {
            throw new LogicException('Payout reservation requires a shared database connection.');
        }
    }

    public function locked(string $operationId): ?PayoutReservationRecord
    {
        $connection = (new AffiliatePayoutOperation)->getConnection();
        $this->assertSharedTransaction(spl_object_id($connection));

        $operation = AffiliatePayoutOperation::query()->forOwner()->lockForUpdate()->find($operationId);

        return $operation === null ? null : new PayoutReservationRecord(
            affiliateId: $operation->affiliate_id,
            payoutId: $operation->affiliate_payout_id,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            sequence: $operation->payout_sequence,
            fundsReleased: $operation->funds_released_at !== null,
        );
    }
}
