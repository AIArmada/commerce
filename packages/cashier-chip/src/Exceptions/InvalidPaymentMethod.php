<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class InvalidPaymentMethod extends CashierChipException
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
