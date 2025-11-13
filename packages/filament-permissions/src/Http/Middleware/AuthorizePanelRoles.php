<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthorizePanelRoles
{
    public function handle(Request $request, Closure $next): mixed
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null) {
            return $next($request);
        }

        $panelId = (string) $panel->getId();

        $enabled = (bool) config('filament-permissions.features.panel_role_authorization');
        if ($enabled === false) {
            return $next($request);
        }

        /** @var Authenticatable|null $user */
        $user = $request->user();
        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        // Super Admin bypass (universal access)
        $superAdminRole = (string) config('filament-permissions.super_admin_role');
        if ($superAdminRole !== '' && method_exists($user, 'hasRole')) {
            if ((bool) $user->hasRole($superAdminRole)) {
                return $next($request);
            }
        }

        $roles = (array) (config('filament-permissions.panel_roles.'.$panelId) ?? []);
        if ($roles === [] || count($roles) === 0) {
            return $next($request);
        }

        $guardMap = (array) config('filament-permissions.panel_guard_map');
        $guard = isset($guardMap[$panelId]) ? (string) $guardMap[$panelId] : null;

        if (method_exists($user, 'hasAnyRole')) {
            // Spatie Permission v6 optionally accepts guard on hasAnyRole; if not supported, ignore guard.
            $authorized = $guard !== null
                ? (bool) $user->hasAnyRole($roles, $guard)
                : (bool) $user->hasAnyRole($roles);

            if ($authorized) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException();
    }
}
