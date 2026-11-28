<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\Cashier\Support\GatewayDetector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use RuntimeException;
use Throwable;

final class ManageCustomerPaymentMethod
{
    public function setDefault(Model $user, string $gateway, string $methodId): void
    {
        $this->authorize($user, $gateway, $methodId);

        if ($gateway === 'stripe' && method_exists($user, 'updateDefaultPaymentMethod')) {
            $user->updateDefaultPaymentMethod($methodId);

            return;
        }

        if ($gateway === 'chip' && method_exists($user, 'updateDefaultChipPaymentMethod')) {
            $user->updateDefaultChipPaymentMethod($methodId);

            return;
        }

        throw new RuntimeException('Setting a default payment method is unsupported.');
    }

    public function delete(Model $user, string $gateway, string $methodId): void
    {
        $this->authorize($user, $gateway, $methodId);

        if ($gateway === 'stripe' && method_exists($user, 'findPaymentMethod')) {
            $method = $user->findPaymentMethod($methodId);

            if (! is_object($method) || ! method_exists($method, 'delete')) {
                throw new AuthorizationException('Payment method is not accessible.');
            }

            $method->delete();

            return;
        }

        if ($gateway === 'chip' && method_exists($user, 'deleteChipPaymentMethod')) {
            $user->deleteChipPaymentMethod($methodId);

            return;
        }

        throw new RuntimeException('Deleting a payment method is unsupported.');
    }

    private function authorize(Model $user, string $gateway, string $methodId): void
    {
        if (! $user->exists || mb_trim($methodId) === '' || ! app(GatewayDetector::class)->isAvailable($gateway)) {
            throw new AuthorizationException('Payment method is not accessible.');
        }

        if ($user instanceof BillableContract) {
            ActionGuard::assertBillableInScope($user);
        }

        if (! $this->userOwnsPaymentMethod($user, $gateway, $methodId)) {
            throw new AuthorizationException('Payment method is not accessible.');
        }
    }

    private function userOwnsPaymentMethod(Model $user, string $gateway, string $methodId): bool
    {
        if ($gateway === 'stripe') {
            if (! method_exists($user, 'paymentMethods')) {
                return false;
            }

            try {
                /** @var Collection<int, mixed>|Enumerable<int, mixed>|array<int, mixed> $methods */
                $methods = $user->paymentMethods();

                return collect($methods)->contains(
                    fn (mixed $method): bool => (string) data_get($method, 'id') === $methodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        if ($gateway === 'chip') {
            if (! method_exists($user, 'chipPaymentMethods')) {
                return false;
            }

            try {
                /** @var Collection<int, mixed>|Enumerable<int, mixed>|array<int, mixed> $methods */
                $methods = $user->chipPaymentMethods();

                return collect($methods)->contains(
                    fn (mixed $method): bool => (string) data_get($method, 'id') === $methodId
                );
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }
}

