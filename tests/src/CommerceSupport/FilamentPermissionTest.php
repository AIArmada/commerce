<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('rechecks super-admin roles without authz instead of caching an uninvalidated verdict', function (): void {
    $process = new Process([PHP_BINARY, '-r', <<<'PHP'
        $loader = require 'vendor/autoload.php';
        $loader->unregister();
        spl_autoload_register(static function (string $class) use ($loader): void {
            if (! str_starts_with($class, 'AIArmada\\Authz\\')) {
                $loader->loadClass($class);
            }
        });

        $app = new Illuminate\Container\Container;
        Illuminate\Container\Container::setInstance($app);
        $app->instance('config', new Illuminate\Config\Repository([
            'authz' => ['super_admin_role' => 'Super Admin'],
        ]));
        $registrar = Mockery::mock(Spatie\Permission\PermissionRegistrar::class);
        $registrar->teams = true;
        $app->instance(Spatie\Permission\PermissionRegistrar::class, $registrar);

        $user = new class implements Illuminate\Contracts\Auth\Access\Authorizable
        {
            public bool $assigned = true;

            public function can(mixed $abilities, mixed $arguments = []): bool
            {
                return false;
            }

            public function hasRole(string $role): bool
            {
                return $this->assigned && $role === 'Super Admin';
            }
        };

        if (class_exists(AIArmada\Authz\Support\UserRoleChecker::class)) {
            throw new RuntimeException('Authz must be absent for the standalone regression.');
        }

        $check = new ReflectionMethod(AIArmada\CommerceSupport\Support\FilamentPermission::class, 'isSuperAdmin');
        $granted = $check->invoke(null, $user);
        $user->assigned = false;
        $revoked = $check->invoke(null, $user);
        $user->assigned = true;
        $regranted = $check->invoke(null, $user);

        if ($granted !== true || $revoked !== false || $regranted !== true || $registrar->teams !== true) {
            throw new RuntimeException('Standalone role changes must be visible and team scoping restored.');
        }
        PHP,
    ], dirname(__DIR__, 3));

    $process->mustRun();

    expect($process->isSuccessful())->toBeTrue();
});
