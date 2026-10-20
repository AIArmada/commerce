<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Session\SessionManager;
use RuntimeException;
use Throwable;

/**
 * Resolves cart identifiers with one rule set shared by the cart manager and
 * the migration helpers: explicit scalar user keys are preserved byte-for-byte,
 * authenticated users are never truncated or replaced by a guest session, and
 * missing identifiers fail clearly instead of silently degrading to another
 * identity.
 */
final class CartIdentifierResolver
{
    /**
     * Resolve the ambient cart identifier: explicit custom identifier, then the
     * authenticated user ID, then the session ID. Throws when none is available.
     */
    public function resolve(?string $customIdentifier = null): string
    {
        if ($customIdentifier !== null) {
            return $customIdentifier;
        }

        $authenticatedId = $this->resolveAuthenticatedUserId();

        if ($authenticatedId !== null) {
            return $authenticatedId;
        }

        $sessionId = $this->resolveSessionId();

        if ($sessionId !== null && $sessionId !== '') {
            return $sessionId;
        }

        throw new RuntimeException(
            'Cart identifier cannot be determined: neither auth nor session services are available'
        );
    }

    /**
     * Resolve the authenticated user ID as a string without numeric coercion.
     * Throws when the guard is authenticated but exposes no usable ID.
     */
    public function resolveAuthenticatedUserId(): ?string
    {
        if (! $this->isAuthenticated()) {
            return null;
        }

        $identifier = $this->guardId();

        if ($identifier === null || $identifier === '') {
            throw new RuntimeException(
                'Cart identifier cannot be determined: the authenticated user has no ID'
            );
        }

        return $identifier;
    }

    /**
     * Resolve the current session ID, or null when no session is available.
     */
    public function resolveSessionId(): ?string
    {
        try {
            if (! app()->bound('session')) {
                return null;
            }

            return app(SessionManager::class)->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function isAuthenticated(): bool
    {
        try {
            return app()->bound('auth') && app(Factory::class)->guard()->check();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Guard ID as a string, or null when the guard exposes no scalar key.
     */
    private function guardId(): ?string
    {
        try {
            $id = app(Factory::class)->guard()->id();
        } catch (Throwable) {
            return null;
        }

        return is_string($id) || is_int($id) ? (string) $id : null;
    }
}
