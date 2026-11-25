<?php

declare(strict_types=1);

use AIArmada\Authz\EntryPoints\Console\Concerns\Prohibitable;
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\EntryPoints\Console\SuperAdminCommand;
use AIArmada\Authz\Features\Authorization\EntryPoints\Console\SyncAuthzCommand;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;

describe('GeneratePoliciesCommand', function (): void {
    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(GeneratePoliciesCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(GeneratePoliciesCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:policies');
    });

    it('generates owner-aware checks for record policy methods', function (): void {
        $command = app(GeneratePoliciesCommand::class);
        $methodsReflection = new ReflectionMethod($command, 'generatePolicyMethods');
        $stubReflection = new ReflectionMethod($command, 'getPolicyStub');

        $methods = $methodsReflection->invoke($command, 'App\\Models\\Order', [
            'order.view' => 'View',
            'order.viewAny' => 'View Any',
        ]);
        $stub = $stubReflection->invoke($command);

        expect($methods)
            ->toContain('isRecordInCurrentOwnerScope($order)')
            ->toContain("return \$user->can('order.view');")
            ->and($stub)
            ->toContain('OwnerWriteGuard::findOrFailForOwner')
            ->toContain('NoCurrentOwnerException');
    });
});

describe('SeederCommand', function (): void {
    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SeederCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SeederCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:seeder');
    });

    it('formats permissions by guard name', function (): void {
        $permission = new Permission;
        $permission->name = 'docs.view';
        $permission->guard_name = 'api';

        $command = app(SeederCommand::class);
        $method = new ReflectionMethod($command, 'formatPermissionsArray');

        expect($method->invoke($command, collect([$permission])))
            ->toBe(['api' => ['docs.view']]);
    });

    it('formats roles by guard name', function (): void {
        $permission = new Permission;
        $permission->name = 'docs.view';
        $permission->guard_name = 'api';

        $role = new Role;
        $role->name = 'manager';
        $role->guard_name = 'api';
        $role->setRelation('permissions', collect([$permission]));

        $command = app(SeederCommand::class);
        $method = new ReflectionMethod($command, 'formatRolesArray');

        expect($method->invoke($command, collect([$role])))
            ->toBe([
                'manager' => [
                    'guard' => 'api',
                    'permissions' => ['docs.view'],
                ],
            ]);
    });

    it('generates seeders with authz-owned models', function (): void {
        $command = app(SeederCommand::class);
        $method = new ReflectionMethod($command, 'generateSeederContent');

        $content = $method->invoke($command, collect(), collect(), 'all');

        expect($content)
            ->toContain('use AIArmada\\Authz\\Features\\Authorization\\Models\\Permission;')
            ->toContain('use AIArmada\\Authz\\Features\\Authorization\\Models\\Role;')
            ->not->toContain('use Spatie\\Permission\\Models\\Permission;')
            ->not->toContain('use Spatie\\Permission\\Models\\Role;');
    });
});

describe('SuperAdminCommand', function (): void {
    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SuperAdminCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SuperAdminCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:super-admin');
    });
});

describe('SyncAuthzCommand', function (): void {
    it('uses prohibitable trait', function (): void {
        $traits = class_uses_recursive(SyncAuthzCommand::class);

        expect($traits)->toHaveKey(Prohibitable::class);
    });

    it('has correct command signature', function (): void {
        $command = app(SyncAuthzCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:sync');
    });
});

describe('DiscoverCommand', function (): void {
    it('has correct command signature', function (): void {
        $command = app(DiscoverCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:discover');
    });

    it('persists discovered permissions through the role administration api', function (): void {
        $calls = [];

        $api = Mockery::mock(RoleAdministrationApi::class);
        $api->shouldReceive('ensurePermissions')
            ->andReturnUsing(function (string $guard, array $permissions) use (&$calls): void {
                $calls[] = [$guard, $permissions];
            });

        app()->instance(RoleAdministrationApi::class, $api);

        $command = app(DiscoverCommand::class);
        $method = new ReflectionMethod($command, 'createPermissions');
        $method->setAccessible(true);
        $method->invoke($command, ['orders.view', 'orders.create']);

        $configuredGuards = array_keys((array) config('auth.guards', []));
        $expectedGuards = array_values(array_filter(
            (array) config('authz.guards', ['web']),
            fn (string $guard): bool => in_array($guard, $configuredGuards, true),
        ));

        expect($calls)->not->toBeEmpty()
            ->and(array_values(array_unique(array_column($calls, 0))))->toBe($expectedGuards)
            ->and(collect($calls)->every(fn (array $call): bool => $call[1] === ['orders.view', 'orders.create']))->toBeTrue();
    });
});

describe('SeederCommand guard validation', function (): void {
    it('drops guards that are not configured in the auth config', function (): void {
        config()->set('authz.guards', ['web', 'unknown-guard']);

        $command = app(SeederCommand::class);
        $method = new ReflectionMethod($command, 'validGuards');
        $method->setAccessible(true);

        expect($method->invoke($command))->toBe(['web']);
    });
});
