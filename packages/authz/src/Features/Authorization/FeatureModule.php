<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization;

use AIArmada\Authz\Features\Authorization\Api\AuthorizationApi;
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Support\AuthzScopeContext;
use AIArmada\Authz\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Authz\Services\PermissionKeyBuilder;
use AIArmada\Authz\Services\WildcardPermissionResolver;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(AuthzScopeContext::class);
        $app->singleton(PermissionKeyBuilder::class);
        $app->singleton(WildcardPermissionResolver::class);
        $app->singleton(Authz::class);
        $app->bind(RoleAdministrationApi::class, RoleAdministration::class);
        $app->singleton(AuthorizationApi::class, static fn (Application $app): AuthorizationApi => $app->make(Authz::class));
    }

    public function boot(Application $app): void {}
}
