<?php

declare(strict_types=1);

namespace AIArmada\Authz\Services;

use AIArmada\Authz\Events\LeaveImpersonation;
use AIArmada\Authz\Events\TakeImpersonation;
use AIArmada\Authz\Guard\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * ImpersonateManager service.
 *
 * Manages user impersonation using session-based state tracking.
 * Uses custom SessionGuard methods to switch identities without auth events.
 */
class ImpersonateManager
{
    public const SESSION_IMPERSONATOR_ID = 'authz_impersonator_id';

    public const SESSION_IMPERSONATOR_GUARD = 'authz_impersonator_guard';

    public const SESSION_IMPERSONATED_GUARD = 'authz_impersonated_guard';

    public const SESSION_BACK_TO = 'authz_impersonator_back_to';

    public function __construct(
        private readonly Application $app
    ) {}

    /**
     * Check if currently impersonating a user.
     */
    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_IMPERSONATOR_ID);
    }

    /**
     * Get the original impersonator's ID.
     */
    public function getImpersonatorId(): mixed
    {
        return session(self::SESSION_IMPERSONATOR_ID);
    }

    /**
     * Get the original impersonator's guard name.
     */
    public function getImpersonatorGuardName(): ?string
    {
        return session(self::SESSION_IMPERSONATOR_GUARD);
    }

    /**
     * Get the guard name being used for impersonation.
     */
    public function getImpersonatedGuardName(): ?string
    {
        return session(self::SESSION_IMPERSONATED_GUARD);
    }

    /**
     * Get the URL to redirect back to after leaving impersonation.
     */
    public function getBackTo(): ?string
    {
        return session(self::SESSION_BACK_TO);
    }

    /**
     * Get the original impersonator user.
     */
    public function getImpersonator(): ?Authenticatable
    {
        $id = $this->getImpersonatorId();

        if ($id === null) {
            return null;
        }

        return $this->findUserById($id, $this->getImpersonatorGuardName());
    }

    /**
     * Take impersonation of a user.
     *
     * @param  Authenticatable  $from  The current user (impersonator)
     * @param  Authenticatable  $to  The user to impersonate
     * @param  string|null  $guardName  The guard to use for impersonation
     * @param  string|null  $backTo  URL to redirect back to when leaving
     */
    public function take(Authenticatable $from, Authenticatable $to, ?string $guardName = null, ?string $backTo = null): bool
    {
        if ($this->isImpersonating()) {
            return false;
        }

        $targetGuardName = $guardName ?? $this->getDefaultGuard();
        $sourceGuardName = $this->getCurrentAuthGuardName();

        if ($sourceGuardName === null) {
            return false;
        }

        try {
            $this->writeImpersonationState($from, $sourceGuardName, $targetGuardName, $backTo);
            $this->switchIdentity($sourceGuardName, $targetGuardName, $to);
            $this->app['events']->dispatch(new TakeImpersonation($from, $to));
            session()->save();
        } catch (Throwable $exception) {
            $this->clearGuardIdentity($targetGuardName);
            $restored = $this->restoreUser($from, $sourceGuardName);

            if (! $restored) {
                $this->clearGuardIdentity($sourceGuardName);
            }

            $this->clear();
            session()->save();
            report($exception);

            return false;
        }

        return true;
    }

    public function leave(): bool
    {
        if (! $this->isImpersonating()) {
            return false;
        }

        $impersonatedGuardName = $this->getImpersonatedGuardName();
        $impersonatorGuardName = $this->getImpersonatorGuardName();
        $impersonatorId = $this->getImpersonatorId();
        $backTo = $this->getBackTo();

        if ($impersonatedGuardName === null || $impersonatorGuardName === null || $impersonatorId === null) {
            $this->clear();

            return false;
        }

        $impersonated = $this->app['auth']->guard($impersonatedGuardName)->user();
        $impersonator = $this->findUserById($impersonatorId, $impersonatorGuardName);

        if ($impersonated === null || $impersonator === null) {
            $this->clear();

            return false;
        }

        try {
            $this->switchIdentity($impersonatedGuardName, $impersonatorGuardName, $impersonator);
            $this->app['events']->dispatch(new LeaveImpersonation($impersonator, $impersonated));
            $this->clear();
            session()->forget('password_hash_' . $impersonatedGuardName);
            session()->save();
        } catch (Throwable $exception) {
            $this->clearGuardIdentity($impersonatorGuardName);
            $restored = $this->restoreUser($impersonated, $impersonatedGuardName);

            if ($restored) {
                $this->writeImpersonationState($impersonator, $impersonatorGuardName, $impersonatedGuardName, $backTo);
            } else {
                $this->clearGuardIdentity($impersonatedGuardName);
                $this->clear();
            }

            session()->save();
            report($exception);

            return false;
        }

        return true;
    }

    /**
     * Clear impersonation session data.
     */
    public function clear(): void
    {
        session()->forget(self::SESSION_IMPERSONATOR_ID);
        session()->forget(self::SESSION_IMPERSONATOR_GUARD);
        session()->forget(self::SESSION_IMPERSONATED_GUARD);
        session()->forget(self::SESSION_BACK_TO);
    }

    /**
     * Find a user by ID using the specified guard's user provider.
     */
    public function findUserById(mixed $id, ?string $guardName = null): ?Authenticatable
    {
        $guardName = $guardName ?? $this->getDefaultGuard();
        $providerName = $this->app['config']->get("auth.guards.{$guardName}.provider");

        if (empty($providerName)) {
            return null;
        }

        try {
            $userProvider = $this->app['auth']->createUserProvider($providerName);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $userProvider?->retrieveById($id);
    }

    /**
     * Get the current authenticated guard name.
     */
    public function getCurrentAuthGuardName(): ?string
    {
        $guards = array_keys($this->app['config']->get('auth.guards', []));

        foreach ($guards as $guard) {
            if ($this->app['auth']->guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * Get the default guard for impersonation.
     */
    public function getDefaultGuard(): string
    {
        return (string) config('authz.impersonate.guard', 'web');
    }

    /**
     * Sanitize the back-to URL to prevent open redirect via a controlled Referer header.
     *
     * Accepts relative paths (e.g. /admin) and absolute same-host URLs.
     * Rejects any URL pointing to a different host.
     */
    private function writeImpersonationState(
        Authenticatable $impersonator,
        string $sourceGuardName,
        string $targetGuardName,
        ?string $backTo,
    ): void {
        session()->put(self::SESSION_IMPERSONATOR_ID, $impersonator->getAuthIdentifier());
        session()->put(self::SESSION_IMPERSONATOR_GUARD, $sourceGuardName);
        session()->put(self::SESSION_IMPERSONATED_GUARD, $targetGuardName);
        session()->forget(self::SESSION_BACK_TO);

        if ($backTo !== null) {
            session()->put(self::SESSION_BACK_TO, $this->sanitizeBackToUrl($backTo));
        }
    }

    private function switchIdentity(string $sourceGuardName, string $targetGuardName, Authenticatable $target): void
    {
        $this->getSessionGuard($sourceGuardName)->quietLogout();
        $this->getSessionGuard($targetGuardName)->quietLogin($target);

        $this->updatePasswordHashInSession($target, $targetGuardName);
        $this->rotateSessionId();
    }

    private function rotateSessionId(): void
    {
        session()->migrate(true);
        session()->regenerateToken();
    }

    private function sanitizeBackToUrl(string $url): string
    {
        if ($url === '') {
            return '/';
        }

        // Relative path — always safe.
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        // Parse absolute URL and compare host against current request host.
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return '/';
        }

        $requestHost = request()->getHost();

        if (mb_strtolower($parsed['host']) !== mb_strtolower($requestHost)) {
            return '/';
        }

        return $url;
    }

    private function getSessionGuard(string $guardName): SessionGuard
    {
        $guard = $this->app['auth']->guard($guardName);

        if (! $guard instanceof SessionGuard) {
            throw new LogicException("Authz impersonation requires the authz session guard; [{$guardName}] is not configured with it.");
        }

        return $guard;
    }

    /**
     * Update the password hash in session for the given user.
     *
     * This is required to prevent AuthenticateSession middleware from
     * logging out the user when it validates the password hash.
     */
    private function updatePasswordHashInSession(Authenticatable $user, string $guardName): void
    {
        $passwordHash = $user->getAuthPassword();

        if (empty($passwordHash)) {
            return;
        }

        $guard = $this->getSessionGuard($guardName);
        $hashedPassword = $guard->hashPasswordForCookie($passwordHash);

        session()->put('password_hash_' . $guardName, $hashedPassword);
    }

    private function restoreUser(Authenticatable $user, string $guardName): bool
    {
        try {
            $this->getSessionGuard($guardName)->quietLogin($user);

            $this->updatePasswordHashInSession($user, $guardName);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    private function clearGuardIdentity(string $guardName): void
    {
        try {
            $this->getSessionGuard($guardName)->quietLogout();
        } catch (Throwable $exception) {
            report($exception);
        }

        session()->forget('password_hash_' . $guardName);
    }
}
