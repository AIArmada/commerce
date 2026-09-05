<?php

declare(strict_types=1);

namespace AIArmada\Authz\Guard;

use Illuminate\Auth\SessionGuard as BaseSessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Custom SessionGuard with quiet login/logout methods.
 *
 * These methods allow switching users without firing auth events or touching
 * the remember token. quietLogin rotates the session identifier while keeping
 * the CSRF token valid for Livewire and Filament requests.
 */
class SessionGuard extends BaseSessionGuard
{
    /**
     * Log a user into the application without firing the Login event or
     * regenerating the CSRF token.
     */
    public function quietLogin(Authenticatable $user): void
    {
        $this->updateSession($user->getAuthIdentifier());
        $this->setUser($user);
    }

    /**
     * Logout the user without:
     * - Updating the remember_token
     * - Firing the Logout event
     * - Regenerating the session
     */
    public function quietLogout(): void
    {
        $this->clearUserDataFromStorage();
        $this->user = null;
        $this->loggedOut = true;
    }
}
