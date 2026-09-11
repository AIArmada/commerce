<?php

declare(strict_types=1);

namespace AIArmada\Products\Exceptions;

use RuntimeException;

final class VariantGenerationLimitExceeded extends RuntimeException
{
    public function __construct(int $requested, int $maximum)
    {
        parent::__construct(sprintf(
            'Generating %d variants exceeds the configured maximum of %d.',
            $requested,
            $maximum,
        ));
    }
}
