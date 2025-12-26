<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use AIArmada\Cashier\Contracts\PaymentContract;
use Exception;

class IncompletePayment extends Exception
{
    /**
     * The payment instance.
     */
    public PaymentContract $payment;

    /**
     * Create a new IncompletePayment exception.
     */
    public function __construct(PaymentContract $payment, string $message = '')
    {
        parent::__construct($message);

        $this->payment = $payment;
    }

    /**
     * Get the payment instance.
     */
    public function payment(): PaymentContract
    {
        return $this->payment;
    }
}
