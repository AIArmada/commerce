<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CustomerAlreadyCreated extends Exception
{
    /**
     * Create a new CustomerAlreadyCreated exception.
     *
     * @param  Model  $owner
     * @return static
     */
    public static function exists($owner)
    {
        return new static(
            class_basename($owner) . " is already a CHIP customer with ID {$owner->chip_id}."
        );
    }
}
