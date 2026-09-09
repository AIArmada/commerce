<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Exceptions;

use InvalidArgumentException;

final class InvalidCurrencyException extends InvalidArgumentException
{
    public function __construct(string $currency)
    {
        parent::__construct(sprintf('Unsupported currency code [%s].', $currency));
    }
}
