<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Queries;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CustomerPaymentMethodsQuery
{
    /** @return Collection<int, PaymentMethodContract> */
    public function records(Model $user, string $gateway): Collection
    {
        if (! $user instanceof BillableContract || ! $user->exists || ! app(GatewayDetector::class)->isAvailable($gateway)) {
            return collect();
        }

        ActionGuard::assertBillableInScope($user);

        return Cashier::gateway($gateway)->paymentMethods($user)
            ->filter(static fn (PaymentMethodContract $method): bool => $method->gateway() === $gateway && $method->id() !== '')
            ->values();
    }

    /** @return array<string, Collection<int, mixed>> */
    public function getForUser(Model $user): array
    {
        if (! $user instanceof BillableContract || ! $user->exists) {
            return [];
        }

        ActionGuard::assertBillableInScope($user);
        $methods = [];

        foreach (app(GatewayDetector::class)->availableGateways() as $gateway) {
            try {
                $records = $this->records($user, $gateway);

                if ($records->isEmpty()) {
                    continue;
                }

                $default = Cashier::gateway($gateway)->defaultPaymentMethod($user);
                $defaultId = $default?->gateway() === $gateway ? $default->id() : null;

                $methods[$gateway] = $records->map(static fn (PaymentMethodContract $method): array => [
                    'id' => $method->id(),
                    'type' => $method->brand() ?? ($method->type() ?: 'Payment Method'),
                    'last4' => $method->lastFour() ?? '****',
                    'expiry' => $method->expirationMonth() !== null && $method->expirationYear() !== null
                        ? sprintf('%02d/%d', $method->expirationMonth(), $method->expirationYear())
                        : 'N/A',
                    'is_default' => $method->id() === $defaultId,
                ]);
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve customer payment methods.', ['gateway' => $gateway, 'exception' => $e::class]);
            }
        }

        return $methods;
    }

    /** @return array<string, array{type: string, last4: string, is_default: bool}> */
    public function defaults(Model $user): array
    {
        if (! $user instanceof BillableContract || ! $user->exists) {
            return [];
        }

        ActionGuard::assertBillableInScope($user);
        $methods = [];

        foreach (app(GatewayDetector::class)->availableGateways() as $gateway) {
            try {
                $default = Cashier::gateway($gateway)->defaultPaymentMethod($user);

                if ($default !== null && $default->gateway() === $gateway && $default->id() !== '') {
                    $methods[$gateway] = [
                        'type' => $default->brand() ?? ($default->type() ?: 'Payment Method'),
                        'last4' => $default->lastFour() ?? '****',
                        'is_default' => true,
                    ];
                }
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve default payment method.', ['gateway' => $gateway, 'exception' => $e::class]);
            }
        }

        return $methods;
    }
}
