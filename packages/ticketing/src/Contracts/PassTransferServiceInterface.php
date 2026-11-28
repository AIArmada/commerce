<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Data\TransferInputData;

interface PassTransferServiceInterface
{
    public function transfer(TransferInputData $input): string;

    public function canTransfer(string $passId): bool;
}
