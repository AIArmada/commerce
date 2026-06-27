<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Spatie\Permission\PermissionRegistrar;

it('flushes the permission cache through the public clear cache api', function (): void {
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldReceive('forgetCachedPermissions')->once();

    app()->instance(PermissionRegistrar::class, $registrar);

    app(Authz::class)->clearCache();
});

it('updates the impersonated password hash through the target guard', function (): void {
    $guard = new class
    {
        public function hashPasswordForCookie(string $passwordHash): string
        {
            return 'cookie:' . $passwordHash;
        }
    };

    $auth = new class($guard)
    {
        public ?string $requestedGuard = null;

        public function __construct(
            private readonly object $guard,
        ) {}

        public function guard(?string $name = null): object
        {
            $this->requestedGuard = $name;

            return $this->guard;
        }
    };

    app()->instance('auth', $auth);

    $user = new User;
    $user->forceFill([
        'id' => 'target-user',
        'password' => 'hashed-password',
    ]);

    $manager = new ImpersonateManager(app());
    $method = new ReflectionMethod($manager, 'updatePasswordHashInSession');
    $method->setAccessible(true);
    $method->invoke($manager, $user, 'admin');

    expect($auth->requestedGuard)->toBe('admin')
        ->and(session('password_hash_admin'))->toBe('cookie:hashed-password');
});

it('resolves the super admin command email column from authz config', function (): void {
    config()->set('authz.users.email_column', 'login_email');

    $command = new SuperAdminCommand;
    $method = new ReflectionMethod($command, 'getEmailColumn');
    $method->setAccessible(true);

    expect($method->invoke($command, User::class))->toBe('login_email');
});
