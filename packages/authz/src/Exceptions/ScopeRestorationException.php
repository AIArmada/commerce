<?php

declare(strict_types=1);

namespace AIArmada\Authz\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a failed team-switch setup cannot be rolled back to the
 * previously active scope.
 *
 * Scope state is deliberately fail-loud: continuing after a failed rollback
 * would run authorization checks under an unknown team, so the original
 * setup failure is preserved in the chain while this exception surfaces.
 */
final class ScopeRestorationException extends RuntimeException
{
    public static function forScope(mixed $scope, Throwable $failure, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Unable to restore the previous authorization scope (%s): %s',
                get_debug_type($scope),
                $failure->getMessage(),
            ),
            previous: $previous,
        );
    }
}
