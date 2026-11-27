<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface BatchReservationInvalidator
{
    public function releaseForBatch(string $batchId): void;
}
