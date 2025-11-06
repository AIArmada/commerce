<?php

declare(strict_types=1);

namespace AIArmada\Cart\Exceptions;

final class InvalidCartItemException extends CartException
{
    public function __construct(string $message = 'Invalid cart item')
    {
        parent::__construct($message);
    }
}
