<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutReservationReader
{
    public function assertSharedTransaction(int $connectionId): void;

    public function affiliateIdForOperation(string $operationId, ?string $ownerType, ?string $ownerId, int $connectionId): ?string;

    public function hasOperations(string $payoutId, int $connectionId): bool;

    public function locked(string $operationId): ?PayoutReservationRecord;
}
