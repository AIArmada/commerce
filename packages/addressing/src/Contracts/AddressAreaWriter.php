<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\AddressArea;

interface AddressAreaWriter
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, ?AddressArea $record = null): AddressArea;
}
