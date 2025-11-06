<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

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
    {        // Only capture session ID if user is not already authenticated
        if (! Auth::check()) {
            $currentSessionId = session()->getId();
            $userIdentifier = $this->getUserIdentifier($event->credentials);

            if ($userIdentifier && $currentSessionId) {
                // Store in cache with user identifier as key, expires in 5 minutes
                Cache::put(
                    "cart_migration_{$userIdentifier}",
                    $currentSessionId,
                    now()->addMinutes(5)
                );
            }
        }
    }

    /**
     * Extract user identifier from login credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function getUserIdentifier(array $credentials): ?string
    {
        // Try common credential fields
        return $credentials['email']
            ?? $credentials['username']
            ?? $credentials['phone']
            ?? null;
    }
}
