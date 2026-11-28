<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

interface PassDeliveryServiceInterface
{
    public function deliver(string $passId): void;

    /** @param list<string> $passIds */
    public function deliverMany(array $passIds): void;
}
