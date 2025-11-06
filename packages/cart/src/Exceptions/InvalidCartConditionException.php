<?php

declare(strict_types=1);

namespace AIArmada\Cart\Exceptions;

final class InvalidCartConditionException extends CartException
{
    public function __construct(string $message = 'Invalid cart condition')
    {
        parent::__construct($message);
    }
}
