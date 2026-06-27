<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class InvalidCustomer extends CashierChipException
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
