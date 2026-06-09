<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Exceptions\PaymentFailedException;
use AIArmada\Cashier\Facades\Cashier;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class CreatePayment
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(BillableContract $billable, int $amount, string $paymentMethod, ?string $gateway = null, array $options = []): PaymentContract
    {
        $gatewayName = $gateway ?? config('cashier.default', 'stripe');
        $gateway = $this->resolveGateway($gatewayName);

        try {
            $payment = $gateway->charge($billable, $amount, $paymentMethod, $options);

            if ($payment->isSucceeded()) {
                PaymentSucceeded::dispatch($payment, $gatewayName, $billable);
            } elseif ($payment->isFailed()) {
                PaymentFailed::dispatch($payment, $gatewayName, $billable);

                throw PaymentFailedException::create(
                    gateway: $gatewayName,
                    message: $payment->errorCode() ?? 'payment_failed',
                    details: ['payment_id' => $payment->id(), 'error_code' => $payment->errorCode() ?? 'payment_failed'],
                );
            }

            return $payment;
        } catch (PaymentFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PaymentFailedException::create(
                gateway: $gatewayName,
                message: $e->getMessage(),
                details: ['error_code' => $e->getMessage()],
            );
        }
    }

    private function resolveGateway(string $name): GatewayContract
    {
        return Cashier::gateway($name);
    }
}
