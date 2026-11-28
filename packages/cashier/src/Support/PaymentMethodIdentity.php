<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

final class PaymentMethodIdentity
{
    public static function chipId(mixed $paymentMethod): ?string
    {
        if (is_object($paymentMethod) && method_exists($paymentMethod, 'id')) {
            $paymentMethodId = $paymentMethod->id();

            if (is_string($paymentMethodId) && $paymentMethodId !== '') {
                return $paymentMethodId;
            }
        }

        $paymentMethodId = data_get($paymentMethod, 'id') ?? data_get($paymentMethod, 'recurring_token');

        return is_string($paymentMethodId) && $paymentMethodId !== '' ? $paymentMethodId : null;
    }
}
