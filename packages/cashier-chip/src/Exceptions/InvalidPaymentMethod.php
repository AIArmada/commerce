<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidPaymentMethod extends Exception
{
    /**
     * Create a new InvalidPaymentMethod exception for invalid owner.
     *
     * @param  Model  $owner
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
