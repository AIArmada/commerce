<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentRefunded;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class RefundPayment
{
    use AsAction;

    public function handle(string $paymentId, ?int $amount = null, ?string $gateway = null): PaymentContract
    {
        ActionGuard::nonEmptyString($paymentId, 'payment ID');

        if ($amount !== null && $amount <= 0) {
            throw new InvalidArgumentException('Cashier refund amount must be a positive integer in minor units.');
        }

        $maximum = max(1, (int) config('cashier.billing.max_amount_minor', 100_000_000));

        if ($amount !== null && $amount > $maximum) {
            throw new InvalidArgumentException(sprintf('Cashier refund amount must not exceed %d minor units.', $maximum));
        }

        $gatewayName = ActionGuard::gatewayName($gateway);
        $gateway = Cashier::gateway($gatewayName);

        $payment = $gateway->refund($paymentId, $amount);

        PaymentRefunded::dispatch($payment, $gatewayName);

        return $payment;
    }
}
