<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\PaymentResult;

interface PaymentCompensationInterface
{
    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult;

    public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult;
}
