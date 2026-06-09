<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

use Illuminate\Support\Facades\Cache;

class LoginMigrationIdentifierResolver
{
    /**
     * Extract possible user identifiers from the authenticated user.
     *
     * @return array<int, string>
     */
    public function resolveFromUser(mixed $user): array
    {
        $identifiers = [];

        if (is_object($user)) {
            foreach (['email', 'username', 'phone'] as $field) {
                $value = $user->{$field} ?? null;
                if (is_string($value) && $value !== '') {
                    $identifiers[] = $value;
                }
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Extract possible user identifiers from login credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<int, string>
     */
    public function resolveFromCredentials(array $credentials): array
    {
        $identifiers = [];

        foreach (['email', 'username', 'phone'] as $field) {
            $value = $credentials[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $identifiers[] = $value;
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Find a cached session ID matching any of the given user identifiers.
     */
    public function findCachedSessionId(array $identifiers): ?string
    {
        foreach ($identifiers as $identifier) {
            $sessionId = Cache::pull(LoginMigrationCacheKey::make($identifier));

            if ($sessionId !== null) {
                return $sessionId;
            }
        }

        return null;
    }

    /**
     * Cache the current session ID for the given user identifiers.
     */
    public function cacheSessionForIdentifiers(array $identifiers, string $sessionId, int $ttlMinutes = 5): void
    {
        foreach ($identifiers as $identifier) {
            Cache::put(
                LoginMigrationCacheKey::make($identifier),
                $sessionId,
                now()->addMinutes($ttlMinutes)
            );
        }
    }
}
