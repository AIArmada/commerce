<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

interface GatewayHealthProbe
{
    public function fingerprint(string $gateway): string;

    public function probe(string $gateway): string;
}
