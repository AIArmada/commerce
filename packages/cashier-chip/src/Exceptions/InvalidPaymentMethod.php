<?php

namespace AIArmada\CashierChip\Exceptions;

use Exception;

class InvalidPaymentMethod extends Exception
{
    /**
     * Create a new InvalidPaymentMethod exception for invalid owner.
     *
     * @param  string  $paymentMethodId
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(string $paymentMethodId, $owner)
    {
        return new static(
            "The payment method `{$paymentMethodId}` does not belong to this customer."
        );
    }

    /**
     * Create a new InvalidPaymentMethod exception for missing payment method.
     *
     * @return static
     */
    public static function notFound()
    {
        return new static('No payment method was found.');
    }
}
