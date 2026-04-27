<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Support\LoginMigrationCacheKey;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class HandleUserLoginAttempt
{
    /**
     * Handle the user login attempt event.
     * Store current session ID before authentication regenerates it.
     */
    public function handle(Attempting $event): void
    {
        // Only capture session ID if user is not already authenticated
        if (! Auth::check()) {
            $currentSessionId = session()->getId();
            $userIdentifiers = $this->getUserIdentifiers($event->credentials);

            if ($userIdentifiers !== [] && $currentSessionId) {
                foreach ($userIdentifiers as $userIdentifier) {
                    Cache::put(
                        LoginMigrationCacheKey::make($userIdentifier),
                        $currentSessionId,
                        now()->addMinutes(5)
                    );
                }
            }
        }
    }

    /**
     * Extract possible user identifiers from login credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<int, string>
     */
    private function getUserIdentifiers(array $credentials): array
    {
        return collect([
            $credentials['email'] ?? null,
            $credentials['username'] ?? null,
            $credentials['phone'] ?? null,
        ])
            ->filter(fn (mixed $identifier): bool => is_string($identifier) && $identifier !== '')
            ->unique()
            ->values()
            ->all();
    }
}
