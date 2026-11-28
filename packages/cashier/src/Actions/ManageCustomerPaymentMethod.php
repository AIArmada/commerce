<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Queries\CustomerPaymentMethodsQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

final readonly class ManageCustomerPaymentMethod
{
    public function __construct(private CustomerPaymentMethodsQuery $methods) {}

    public function setDefault(Model $user, string $gateway, #[SensitiveParameter] string $methodId): void
    {
        $this->ownedMethod($user, $gateway, $methodId)->makeDefault();
    }

    public function delete(Model $user, string $gateway, #[SensitiveParameter] string $methodId): void
    {
        $this->ownedMethod($user, $gateway, $methodId)->delete();
    }

    private function ownedMethod(Model $user, string $gateway, #[SensitiveParameter] string $methodId): PaymentMethodContract
    {
        if (mb_trim($methodId) === '') {
            throw new AuthorizationException('Payment method is not accessible.');
        }

        $method = $this->methods->records($user, $gateway)
            ->first(static fn (PaymentMethodContract $method): bool => $method->id() === $methodId);

        if ($method === null) {
            throw new AuthorizationException('Payment method is not accessible.');
        }

        return $method;
    }
}
