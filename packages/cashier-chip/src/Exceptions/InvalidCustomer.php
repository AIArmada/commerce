<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidCustomer extends Exception
{
    /**
     * Create a new InvalidCustomer exception for missing customer.
     *
     * @param  Model  $owner
     * @return static
     */
    public static function notYetCreated($owner)
    {
        return new static(
            class_basename($owner) . ' is not a CHIP customer yet. See the createAsChipCustomer method.'
        );
    }
}
