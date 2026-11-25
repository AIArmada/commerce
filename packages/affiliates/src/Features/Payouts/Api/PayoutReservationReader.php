<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutReservationReader
{
    public function assertSharedTransaction(int $connectionId): void;

    public function locked(string $operationId): ?PayoutReservationRecord;
}
