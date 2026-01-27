<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

final class CheckoutStepException extends CheckoutException
{
    public static function stepNotFound(string $identifier): self
    {
        return new self(
            "Checkout step '{$identifier}' not found",
            ['step_identifier' => $identifier],
        );
    }

    public static function stepValidationFailed(string $identifier, array $errors): self
    {
        return new self(
            "Validation failed for step '{$identifier}'",
            ['step_identifier' => $identifier, 'errors' => $errors],
        );
    }

    public static function dependencyNotMet(string $identifier, string $dependency): self
    {
        return new self(
            "Step '{$identifier}' requires '{$dependency}' to be completed first",
            ['step_identifier' => $identifier, 'dependency' => $dependency],
        );
    }

    public static function stepAlreadyCompleted(string $identifier): self
    {
        return new self(
            "Step '{$identifier}' has already been completed",
            ['step_identifier' => $identifier],
        );
    }
}
