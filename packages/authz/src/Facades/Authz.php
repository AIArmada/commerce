<?php

declare(strict_types=1);

namespace AIArmada\Authz\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Authz\Authz buildPermissionKeyUsing(\Closure $callback)
 * @method static string buildPermissionKey(string $subject, string $action)
 * @method static string|int|null resolveScopeId(mixed $scope)
 * @method static mixed withScope(mixed $scope, callable $callback, ?\Illuminate\Contracts\Auth\Access\Authorizable $user = null)
 * @method static bool userCanInScope(\Illuminate\Contracts\Auth\Access\Authorizable $user, string $ability, mixed $scope)
 * @method static bool userHasPermissionAcrossScopes(\Illuminate\Contracts\Auth\Access\Authorizable $user, string $ability)
 * @method static array getCustomPermissions()
 * @method static void clearCache()
 *
 * @see \AIArmada\Authz\Authz
 */
final class Authz extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AIArmada\Authz\Authz::class;
    }
}
