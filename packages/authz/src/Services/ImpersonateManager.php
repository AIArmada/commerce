<?php

declare(strict_types=1);

namespace AIArmada\Authz\Services;

use AIArmada\Authz\Events\LeaveImpersonation;
use AIArmada\Authz\Events\TakeImpersonation;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Throwable;

/**
 * ImpersonateManager service.
 *
 * Manages user impersonation using session-based state tracking.
 * Uses custom SessionGuard methods to avoid CSRF token regeneration.
 */
class ImpersonateManager
{
    public const SESSION_KEY = 'filament_authz_impersonated_by';

    public const SESSION_GUARD = 'filament_authz_impersonator_guard';

    public const SESSION_GUARD_USING = 'filament_authz_impersonator_guard_using';

    public const SESSION_BACK_TO = 'filament_authz_impersonator_back_to';

    public function __construct(
        private readonly Application $app
    ) {}

    /**
     * Check if currently impersonating a user.
     */
    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * Get the original impersonator's ID.
     */
    public function getImpersonatorId(): mixed
    {
        return session(self::SESSION_KEY);
    }

    /**
     * Get the original impersonator's guard name.
     */
    public function getImpersonatorGuardName(): ?string
    {
        return session(self::SESSION_GUARD);
    }

    /**
     * Get the guard name being used for impersonation.
     */
    public function getImpersonatorGuardUsingName(): ?string
    {
        return session(self::SESSION_GUARD_USING);
    }

    /**
     * Get the URL to redirect back to after leaving impersonation.
     */
    public function getBackTo(): ?string
    {
        return session(self::SESSION_BACK_TO);
    }

    /**
     * Alias for getBackTo().
     */
    public function getBackToUrl(): ?string
    {
        return $this->getBackTo();
    }

    /**
     * Alias for getImpersonatorGuardName().
     */
    public function getImpersonatorGuard(): ?string
    {
        return $this->getImpersonatorGuardName();
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
            $this->rotateSessionId();
            session()->save();
        } catch (Throwable $exception) {
            $this->restoreUser($from, $sourceGuardName);
            session()->forget('password_hash_' . $targetGuardName);
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

        $impersonatedGuardName = $this->getImpersonatorGuardUsingName();
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
            $this->rotateSessionId();
            session()->save();
        } catch (Throwable $exception) {
            $this->restoreUser($impersonated, $impersonatedGuardName);
            $this->writeImpersonationState($impersonator, $impersonatorGuardName, $impersonatedGuardName, $backTo);
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
        session()->forget(self::SESSION_KEY);
        session()->forget(self::SESSION_GUARD);
        session()->forget(self::SESSION_GUARD_USING);
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
     * Get the redirect URL for after leaving impersonation.
     * Always returns to the origin panel where impersonation began.
     */
    public function getLeaveRedirectTo(): ?string
    {
        return $this->getBackTo();
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
        session()->put(self::SESSION_KEY, $impersonator->getAuthIdentifier());
        session()->put(self::SESSION_GUARD, $sourceGuardName);
        session()->put(self::SESSION_GUARD_USING, $targetGuardName);
        session()->forget(self::SESSION_BACK_TO);

        if ($backTo !== null) {
            session()->put(self::SESSION_BACK_TO, $this->sanitizeBackToUrl($backTo));
        }
    }

    private function switchIdentity(string $sourceGuardName, string $targetGuardName, Authenticatable $target): void
    {
        $sourceGuard = $this->app['auth']->guard($sourceGuardName);

        if (method_exists($sourceGuard, 'quietLogout')) {
            $sourceGuard->quietLogout();
        } else {
            $sourceGuard->logout();
        }

        $targetGuard = $this->app['auth']->guard($targetGuardName);

        if (method_exists($targetGuard, 'quietLogin')) {
            $targetGuard->quietLogin($target);
        } else {
            $targetGuard->setUser($target);
            session()->put($this->getAuthSessionKey($targetGuardName), $target->getAuthIdentifier());
        }

        $this->updatePasswordHashInSession($target, $targetGuardName);
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

    /**
     * Get the session key used by Laravel Auth for a guard.
     */
    private function getAuthSessionKey(string $guard): string
    {
        return 'login_' . $guard . '_' . sha1(SessionGuard::class);
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

        /** @var SessionGuard $guard */
        $guard = $this->app['auth']->guard($guardName);

        $hashedPassword = $guard->hashPasswordForCookie($passwordHash);

        session()->put('password_hash_' . $guardName, $hashedPassword);
    }

    private function restoreUser(Authenticatable $user, string $guardName): void
    {
        try {
            $guard = $this->app['auth']->guard($guardName);

            if (method_exists($guard, 'quietLogin')) {
                $guard->quietLogin($user);
            } else {
                $guard->setUser($user);
                session()->put($this->getAuthSessionKey($guardName), $user->getAuthIdentifier());
            }

            $this->updatePasswordHashInSession($user, $guardName);
        } catch (Throwable) {
        }
    }
}
