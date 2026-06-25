<?php

declare(strict_types=1);

use AIArmada\Authz\Console\Commands\SuperAdminCommand;
use AIArmada\Authz\Console\Commands\SyncAuthzCommand;
use AIArmada\Authz\Console\Concerns\Prohibitable;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;

describe('GeneratePoliciesCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(GeneratePoliciesCommand::class))->toBeTrue();
    });

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
});

describe('SeederCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(SeederCommand::class))->toBeTrue();
    });

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
});

describe('SuperAdminCommand', function (): void {
    it('exists', function (): void {
        expect(class_exists(SuperAdminCommand::class))->toBeTrue();
    });

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
    it('exists', function (): void {
        expect(class_exists(SyncAuthzCommand::class))->toBeTrue();
    });

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
    it('exists', function (): void {
        expect(class_exists(DiscoverCommand::class))->toBeTrue();
    });

    it('has correct command signature', function (): void {
        $command = app(DiscoverCommand::class);
        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('signature');

        expect($property->getValue($command))->toContain('authz:discover');
    });
});
