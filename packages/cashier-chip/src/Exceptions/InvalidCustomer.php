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

    /**
     * Create a new InvalidCustomer exception for an orphaned subscription.
     *
     * @param  Model  $subscription
     */
    public static function missingBillable($subscription): static
    {
        return new static(
            'Cannot charge subscription [' . (string) $subscription->getKey() . '] without an associated billable customer.'
        );
    }
}
