<?php

declare(strict_types=1);

namespace AIArmada\Authz\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a requested authorization scope is non-null but cannot be
 * resolved to an identifier in the active team-resolver namespace.
 *
 * Scope resolution is deliberately fail-closed: a scope that cannot be
 * resolved must never fall back to the global (unscoped) permission context.
 */
final class ScopeResolutionException extends RuntimeException
{
    public static function forScope(mixed $scope, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Unable to resolve the requested authorization scope (%s): %s',
                get_debug_type($scope),
                $reason,
            ),
            previous: $previous,
        );
    }
}
