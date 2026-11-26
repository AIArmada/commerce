<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\PostalCode;

interface PostalCodeWriter
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, ?PostalCode $record = null): PostalCode;
}
