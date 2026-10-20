<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use Throwable;

/**
 * A gateway execution failed after it had claimed an attempt generation.
 *
 * Carries the claimed identity out of the payment step so the pipeline can
 * decide under the session row lock whether the failing execution still owns
 * the attempt before it writes step state, compensates, or fails the checkout.
 * Callers of the public checkout API receive the original exception; this
 * wrapper never escapes the service boundary.
 */
final class PaymentAttemptExecutionException extends CheckoutException
{
    private function __construct(
        public readonly int $attemptNumber,
        public readonly ?string $gateway,
        public readonly PaymentFailureOrigin $origin,
        private readonly Throwable $original,
    ) {
        parent::__construct(
            $original->getMessage() !== '' ? $original->getMessage() : $original::class,
            ['attempt' => $attemptNumber, 'gateway' => $gateway],
            0,
            $original,
        );
    }

    public static function during(PaymentAttemptClaim $claim, Throwable $original): self
    {
        return new self(
            $claim->number,
            $claim->gateway,
            PaymentFailureOrigin::fromClaim($claim),
            $original,
        );
    }

    public static function withOrigin(PaymentFailureOrigin $origin, Throwable $original): self
    {
        return new self(
            $origin->attempts,
            null,
            $origin,
            $original,
        );
    }

    public function getOriginal(): Throwable
    {
        return $this->original;
    }
}
