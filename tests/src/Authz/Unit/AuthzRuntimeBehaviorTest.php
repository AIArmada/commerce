<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\Authz\Support\WildcardPermissionCache;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

it('flushes the permission cache through the public clear cache api', function (): void {
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldReceive('forgetCachedPermissions')->once();

    app()->instance(PermissionRegistrar::class, $registrar);

    app(Authz::class)->clearCache();
});

it('checks global roles without leaking the active team scope', function (): void {
    setPermissionsTeamId(null);

    $user = User::query()->create([
        'name' => 'Global Role User',
        'email' => 'global-role-user@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create([
        'name' => 'global-super-admin',
        'guard_name' => 'web',
    ]);

    $user->assignRole($role);
    $owner = app(OwnerResolverInterface::class)->resolve();
    expect($owner)->not->toBeNull();

    setPermissionsTeamId($owner->getKey());

    expect(UserRoleChecker::hasRole($user, 'global-super-admin'))->toBeFalse()
        ->and(UserRoleChecker::hasGlobalRole($user, 'global-super-admin'))->toBeTrue()
        ->and(getPermissionsTeamId())->toBe($owner->getKey());
});

it('caches wildcard permissions for the current request', function (): void {
    $user = new class
    {
        public int $permissionCalls = 0;

        public function getAuthIdentifier(): string
        {
            return 'wildcard-user';
        }

        public function getAllPermissions(): Collection
        {
            $this->permissionCalls++;

            return collect([
                (object) ['name' => 'orders.*'],
                (object) ['name' => 'orders.view'],
            ]);
        }
    };

    $cache = app(WildcardPermissionCache::class);

    expect($cache->get($user))->toBe(['orders.*'])
        ->and($cache->get($user))->toBe(['orders.*'])
        ->and($user->permissionCalls)->toBe(1);

    $cache->clear();
    $cache->get($user);

    expect($user->permissionCalls)->toBe(2);
});

it('resolves the super admin command email column from authz config', function (): void {
    config()->set('authz.users.email_column', 'login_email');

    $command = new SuperAdminCommand;
    $method = new ReflectionMethod($command, 'getEmailColumn');
    $method->setAccessible(true);

    expect($method->invoke($command, User::class))->toBe('login_email');
});
