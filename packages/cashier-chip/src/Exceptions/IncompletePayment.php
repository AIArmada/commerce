<?php

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use AIArmada\CashierChip\Payment;

class IncompletePayment extends Exception
{
    /**
     * The CHIP Payment instance.
     *
     * @var \AIArmada\CashierChip\Payment
     */
    public Payment $payment;

    /**
     * Create a new IncompletePayment exception.
     *
     * @param  \AIArmada\CashierChip\Payment  $payment
     * @param  string  $message
     * @return void
     */
    public function __construct(Payment $payment, string $message = '')
    {
        parent::__construct($message);

        $this->payment = $payment;
    }

    /**
     * Create a new IncompletePayment exception for a payment that requires redirect.
     *
     * @param  \AIArmada\CashierChip\Payment  $payment
     * @return static
     */
    public static function requiresRedirect(Payment $payment)
    {
        return new static(
            $payment,
            'The payment requires a redirect to the checkout page to complete.'
        );
    }

    /**
     * Create a new IncompletePayment exception for a failed payment.
     *
     * @param  \AIArmada\CashierChip\Payment  $payment
     * @return static
     */
    public static function failed(Payment $payment)
    {
        return new static(
            $payment,
            'The payment failed. Please try again or use a different payment method.'
        );
    }

    /**
     * Create a new IncompletePayment exception for an expired payment.
     *
     * @param  \AIArmada\CashierChip\Payment  $payment
     * @return static
     */
    public static function expired(Payment $payment)
    {
        return new static(
            $payment,
            'The payment session has expired. Please create a new payment.'
        );
    }

    /**
     * Get the CHIP Payment instance.
     *
     * @return \AIArmada\CashierChip\Payment
     */
    public function payment(): Payment
    {
        return $this->payment;
    }
}
