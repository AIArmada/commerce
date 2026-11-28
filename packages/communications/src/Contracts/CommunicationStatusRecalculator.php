<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface CommunicationStatusRecalculator
{
    public function handle(string $communicationId): void;
}
