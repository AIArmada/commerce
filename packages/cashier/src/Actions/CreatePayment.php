<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Exceptions\Payment\PaymentFailedException;
use AIArmada\Cashier\Exceptions\PaymentOperationRateLimitedException;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use Illuminate\Support\Facades\Log;
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
        ActionGuard::positiveAmount($amount);
        ActionGuard::nonEmptyString($paymentMethod, 'payment method');
        ActionGuard::assertBillableInScope($billable);

        $gatewayName = ActionGuard::gatewayName($gateway);
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
        } catch (PaymentOperationRateLimitedException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Cashier payment failed.', [
                'gateway' => $gatewayName,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            throw PaymentFailedException::create(
                gateway: $gatewayName,
                message: 'payment_failed',
                details: ['error_code' => 'payment_failed'],
            );
        }
    }

    private function resolveGateway(string $name): GatewayContract
    {
        return Cashier::gateway($name);
    }
}
