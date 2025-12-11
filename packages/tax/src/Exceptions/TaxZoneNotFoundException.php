<?php

declare(strict_types=1);

namespace AIArmada\Tax\Exceptions;

use Exception;

class TaxZoneNotFoundException extends Exception
{
    public function __construct(string $message = 'Tax zone not found')
    {
        parent::__construct($message);
    }
}
