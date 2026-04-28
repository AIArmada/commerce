<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Http\Controllers;

use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class ImpersonateController
{
    public function __invoke(Request $request, string $userId): RedirectResponse
    {
        $currentUser = Filament::auth()->user();
        $guard = (string) config('filament-authz.impersonate.guard', 'web');

        if ($currentUser === null) {
            abort(403, 'Not authenticated');
        }

        if (ImpersonateAction::isImpersonating()) {
            abort(403, 'Already impersonating');
        }

        /** @var class-string<Model&Authenticatable> $userModelClass */
        $userModelClass = self::resolveUserModelClass($guard);

        /** @var Authenticatable|null $targetUser */
        $targetUser = $userModelClass::query()->whereKey($userId)->first();

        if ($targetUser === null) {
            abort(404, 'User not found');
        }

        // Verify permissions
        if ($currentUser->getAuthIdentifier() === $targetUser->getKey()) {
            abort(403, 'Cannot impersonate yourself');
        }

        if (method_exists($currentUser, 'canImpersonate') && ! $currentUser->canImpersonate()) {
            abort(403, 'You cannot impersonate users');
        }

        if (method_exists($targetUser, 'canBeImpersonated') && ! $targetUser->canBeImpersonated()) {
            abort(403, 'This user cannot be impersonated');
        }

        $superAdminRole = config('filament-authz.super_admin_role');
        if ($superAdminRole && method_exists($currentUser, 'hasRole') && ! $currentUser->hasRole($superAdminRole)) {
            abort(403, 'Only super admins can impersonate');
        }

        // Store impersonation session data
        Session::put(ImpersonateAction::SESSION_KEY, $currentUser->getAuthIdentifier());
        Session::put(ImpersonateAction::SESSION_GUARD_KEY, $guard);
        Session::put(ImpersonateAction::SESSION_BACK_TO_KEY, request()->header('referer') ?? Filament::getUrl());

        // Log in as the target user (this will regenerate the session)
        Auth::guard($guard)->login($targetUser);

        $redirectTo = self::sanitizeRedirectPath($request->input('redirect_to', '/'));

        // Redirect to the selected destination (with the new session/CSRF token)
        return redirect($redirectTo)->with('status', __('filament-authz::filament-authz.impersonate.started_message', ['name' => $targetUser->name ?? $targetUser->email ?? 'User']));
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    private static function resolveUserModelClass(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider) || $provider === '') {
            throw new RuntimeException("Auth guard [{$guard}] has no configured provider.");
        }

        $userModelClass = config("auth.providers.{$provider}.model");

        if (! is_string($userModelClass) || $userModelClass === '') {
            throw new RuntimeException("Auth provider [{$provider}] has no configured user model class.");
        }

        if (! class_exists($userModelClass) || ! is_a($userModelClass, Model::class, true) || ! is_a($userModelClass, Authenticatable::class, true)) {
            throw new RuntimeException("Auth provider [{$provider}] model [{$userModelClass}] must implement Model and Authenticatable.");
        }

        return $userModelClass;
    }

    private static function sanitizeRedirectPath(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            return '/';
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\//', $path) === 1) {
            return '/';
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }

        $normalizedPath = '/' . mb_ltrim($path, '/');
        $allowedPaths = self::allowedRedirectPaths();

        return in_array($normalizedPath, $allowedPaths, true) ? $normalizedPath : '/';
    }

    /**
     * @return list<string>
     */
    private static function allowedRedirectPaths(): array
    {
        $paths = ['/'];

        foreach (Filament::getPanels() as $panel) {
            $paths[] = '/' . mb_ltrim((string) $panel->getPath(), '/');
        }

        return array_values(array_unique($paths));
    }
}
