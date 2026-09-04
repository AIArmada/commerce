<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use InvalidArgumentException;

/**
 * Registers payment processors contributed by optional integrations.
 *
 * A provider package can bind its processor and tag the binding with
 * `checkout.payment_processors`. The checkout package then discovers it
 * without requiring a dependency on that provider.
 */
final class RegisterTaggedPaymentProcessors
{
    public const TAG = 'checkout.payment_processors';

    public function register(PaymentGatewayResolver $resolver): void
    {
        foreach (app()->tagged(self::TAG) as $processor) {
            if (! $processor instanceof PaymentProcessorInterface) {
                throw new InvalidArgumentException(
                    'Every checkout payment processor tag must resolve to an instance of ' . PaymentProcessorInterface::class . '.',
                );
            }

            $resolver->register($processor->getIdentifier(), $processor);
        }
    }
}
