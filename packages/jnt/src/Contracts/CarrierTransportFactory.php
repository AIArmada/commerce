<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

interface CarrierTransportFactory
{
    /** @param array<string, mixed> $config */
    public function create(array $config): CarrierTransport;
}
