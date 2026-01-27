<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

final class MissingPaymentGatewayException extends CheckoutException
{
    public static function noGatewayInstalled(): self
    {
        return new self(
            'No payment gateway package is installed. Please install at least one of: aiarmada/cashier, aiarmada/cashier-chip, or aiarmada/chip',
            ['available_packages' => ['aiarmada/cashier', 'aiarmada/cashier-chip', 'aiarmada/chip']],
        );
    }

    public static function gatewayNotFound(string $gateway): self
    {
        return new self(
            "Payment gateway '{$gateway}' is not available",
            ['requested_gateway' => $gateway],
        );
    }
}
