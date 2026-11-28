<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Queries\CustomerPaymentMethodsQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

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

        $paymentMethod = $data['payment_method'] ?? null;

        if ($paymentMethod !== null && $paymentMethod !== '') {
            if (! is_string($paymentMethod) || ! app(CustomerPaymentMethodsQuery::class)->records($billable, $gateway)
                ->contains(static fn (PaymentMethodContract $method): bool => $method->id() === $paymentMethod)) {
                throw new AuthorizationException('Selected payment method is not accessible.');
            }
        }

        $builder = Cashier::gateway($gateway)
            ->newSubscription($billable, $data['type'] ?? 'default', $data['plan_id']);

        if (isset($data['quantity']) && $data['quantity'] > 1) {
            $builder->quantity((int) $data['quantity']);
        }

        if (! empty($data['has_trial']) && ! empty($data['trial_days'])) {
            $builder->trialDays((int) $data['trial_days']);
        }

        if (is_string($paymentMethod) && $paymentMethod !== '') {
            $builder->create($paymentMethod);

            return $billable;
        }

        $builder->create();

        return $billable;
    }
}
