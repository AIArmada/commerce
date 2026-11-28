<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\PaymentMethodIdentity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

final class CreateCustomerSubscription
{
    public function handle(array $data): Model
    {
        $billableModel = (string) config('cashier.models.billable');

        if (! class_exists($billableModel) || ! is_a($billableModel, Model::class, true)) {
            throw new RuntimeException('Configured cashier billable model must be an Eloquent model.');
        }
        $billable = OwnerScopedQuery::apply($billableModel::query())
            ->whereKey($data['billable_id'])
            ->first();

        if ($billable === null) {
            throw new AuthorizationException('Selected customer is not accessible.');
        }

        if (! $billable instanceof BillableContract) {
            throw new RuntimeException('Configured cashier billable model must implement BillableContract.');
        }

        ActionGuard::assertBillableInScope($billable);

        $gateway = $data['gateway'];

        $gatewayDetector = app(GatewayDetector::class);

        if (! $gatewayDetector->isAvailable($gateway)) {
            throw new RuntimeException("Selected gateway is not available: {$gateway}");
        }

        $builder = Cashier::gateway($gateway)
            ->newSubscription($billable, $data['type'] ?? 'default', $data['plan_id']);

        if (isset($data['quantity']) && $data['quantity'] > 1) {
            $builder->quantity((int) $data['quantity']);
        }

        if (! empty($data['has_trial']) && ! empty($data['trial_days'])) {
            $builder->trialDays((int) $data['trial_days']);
        }

        if (! empty($data['payment_method']) && is_string($data['payment_method'])) {
            if (! $this->billableOwnsPaymentMethod($billable, $gateway, $data['payment_method'])) {
                throw new AuthorizationException('Selected payment method is not accessible.');
            }

            $builder->create($data['payment_method']);

            return $billable;
        }

        $builder->create();

        return $billable;
    }

    private function billableOwnsPaymentMethod(BillableContract $billable, string $gateway, string $paymentMethodId): bool
    {
        if ($gateway === 'stripe') {
            if (! method_exists($billable, 'paymentMethods')) {
                return false;
            }

            try {
                $methods = $billable->paymentMethods();

                if (! is_iterable($methods)) {
                    return false;
                }

                /** @var iterable<int, mixed> $methods */
                return collect($methods)->contains(
                    fn (mixed $paymentMethod): bool => (string) data_get($paymentMethod, 'id') === $paymentMethodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        if ($gateway === 'chip') {
            if (! method_exists($billable, 'paymentMethods')) {
                return false;
            }

            try {
                $methods = $billable->paymentMethods();

                if (! is_iterable($methods)) {
                    return false;
                }

                /** @var iterable<int, mixed> $methods */
                return collect($methods)->contains(
                    fn (mixed $paymentMethod): bool => PaymentMethodIdentity::chipId($paymentMethod) === $paymentMethodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }
}
