<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Payment;

use AIArmada\Cashier\Contracts\PaymentContract;

class IncompletePayment extends PaymentException
{
    public PaymentContract $payment;

    public function __construct(PaymentContract $payment, string $message = '')
    {
        parent::__construct($message);

        $this->payment = $payment;
    }

    public function payment(): PaymentContract
    {
        return $this->payment;
    }
}
