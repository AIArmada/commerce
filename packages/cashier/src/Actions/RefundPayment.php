<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentRefunded;
use AIArmada\Cashier\Facades\Cashier;
use Lorisleiva\Actions\Concerns\AsAction;

final class RefundPayment
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(string $paymentId, ?int $amount = null, ?string $gateway = null, array $options = []): PaymentContract
    {
        $gatewayName = $gateway ?? config('cashier.default', 'stripe');
        $gateway = Cashier::gateway($gatewayName);

        $payment = $gateway->refund($paymentId, $amount);

        PaymentRefunded::dispatch($payment, $gatewayName);

        return $payment;
    }
}
