<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\AuthzCacheCommand;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\InstallTraitCommand;
use AIArmada\FilamentAuthz\Console\RoleHierarchyCommand;
use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Console\SnapshotCommand;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Register the command if not already registered
    $this->app->singleton(AuthzCacheCommand::class);
});

afterEach(function (): void {
    Mockery::close();
});

describe('AuthzCacheCommand', function (): void {
    test('authz:cache command with stats action shows statistics', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);
        $cacheService->shouldReceive('getStats')->once()->andReturn([
            'enabled' => true,
            'store' => 'array',
            'ttl' => 3600,
        ]);

        app()->instance(PermissionCacheService::class, $cacheService);

        $exitCode = Artisan::call('authz:cache', ['action' => 'stats']);

        expect($exitCode)->toBe(0);
    });

    test('authz:cache command with warm action warms cache', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);
        $cacheService->shouldReceive('warmRoleCache')->once();

        app()->instance(PermissionCacheService::class, $cacheService);

        $exitCode = Artisan::call('authz:cache', ['action' => 'warm']);

        expect($exitCode)->toBe(0);
    });

    test('authz:cache command with invalid action returns failure', function (): void {
        $cacheService = Mockery::mock(PermissionCacheService::class);
        app()->instance(PermissionCacheService::class, $cacheService);

        // Note: The test with invalid action returns FAILURE because no matching action
        $exitCode = Artisan::call('authz:cache', ['action' => 'invalid']);

        expect($exitCode)->toBe(1);
    });
});

describe('GeneratePoliciesCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new GeneratePoliciesCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');

        $signature = $property->getValue($command);

        expect($signature)->toContain('authz:policies');
        expect($signature)->toContain('--type=');
        expect($signature)->toContain('--resource=');
        expect($signature)->toContain('--model=');
        expect($signature)->toContain('--panel=');
        expect($signature)->toContain('--namespace=');
        expect($signature)->toContain('--force');
        expect($signature)->toContain('--dry-run');
        expect($signature)->toContain('--interactive');
    });

    it('has correct description', function (): void {
        $command = new GeneratePoliciesCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Generate Laravel policies for Filament resources');
    });

    it('extends Command', function (): void {
        expect(GeneratePoliciesCommand::class)->toExtend(Command::class);
    });

    it('has getPolicyType method', function (): void {
        $command = new GeneratePoliciesCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('getPolicyType'))->toBeTrue();
        expect($reflection->getMethod('getPolicyType')->isProtected())->toBeTrue();
    });

    it('has getResources method', function (): void {
        $command = new GeneratePoliciesCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('getResources'))->toBeTrue();
        expect($reflection->getMethod('getResources')->isProtected())->toBeTrue();
    });
});

describe('InstallTraitCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new InstallTraitCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');

        $signature = $property->getValue($command);

        expect($signature)->toContain('authz:install-trait');
        expect($signature)->toContain('file?');
        expect($signature)->toContain('--trait=');
        expect($signature)->toContain('--preview');
        expect($signature)->toContain('--force');
    });

    it('has correct description', function (): void {
        $command = new InstallTraitCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Install authorization traits into your classes');
    });

    it('defines available traits', function (): void {
        $command = new InstallTraitCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('availableTraits');

        $traits = $property->getValue($command);

        expect($traits)->toHaveKey('HasPageAuthz');
        expect($traits)->toHaveKey('HasWidgetAuthz');
        expect($traits)->toHaveKey('HasResourceAuthz');
        expect($traits)->toHaveKey('HasPanelAuthz');
        expect($traits['HasPageAuthz'])->toBe('AIArmada\\FilamentAuthz\\Concerns\\HasPageAuthz');
    });

    it('extends Command', function (): void {
        expect(InstallTraitCommand::class)->toExtend(Command::class);
    });
});

describe('RoleHierarchyCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');

        $signature = $property->getValue($command);

        expect($signature)->toContain('authz:roles-hierarchy');
        expect($signature)->toContain('action?');
        expect($signature)->toContain('--role=');
        expect($signature)->toContain('--parent=');
    });

    it('has correct description', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage role hierarchy');
    });

    it('extends Command', function (): void {
        expect(RoleHierarchyCommand::class)->toExtend(Command::class);
    });

    it('has listRoles method', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('listRoles'))->toBeTrue();
        expect($reflection->getMethod('listRoles')->isProtected())->toBeTrue();
    });

    it('has showTree method', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('showTree'))->toBeTrue();
        expect($reflection->getMethod('showTree')->isProtected())->toBeTrue();
    });

    it('has setParent method', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('setParent'))->toBeTrue();
        expect($reflection->getMethod('setParent')->isProtected())->toBeTrue();
    });

    it('has detachFromParent method', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('detachFromParent'))->toBeTrue();
        expect($reflection->getMethod('detachFromParent')->isProtected())->toBeTrue();
    });

    it('has printRoleTree method', function (): void {
        $command = new RoleHierarchyCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('printRoleTree'))->toBeTrue();
        expect($reflection->getMethod('printRoleTree')->isProtected())->toBeTrue();
    });
});

describe('RoleTemplateCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');

        $signature = $property->getValue($command);

        expect($signature)->toContain('authz:templates');
        expect($signature)->toContain('action?');
        expect($signature)->toContain('--template=');
        expect($signature)->toContain('--role=');
    });

    it('has correct description', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage role templates');
    });

    it('extends Command', function (): void {
        expect(RoleTemplateCommand::class)->toExtend(Command::class);
    });

    it('has listTemplates method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('listTemplates'))->toBeTrue();
    });

    it('has createTemplate method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('createTemplate'))->toBeTrue();
    });

    it('has createRoleFromTemplate method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('createRoleFromTemplate'))->toBeTrue();
    });

    it('has syncRole method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('syncRole'))->toBeTrue();
    });

    it('has syncAllRoles method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('syncAllRoles'))->toBeTrue();
    });

    it('has deleteTemplate method', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('deleteTemplate'))->toBeTrue();
    });
});

describe('SnapshotCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');

        $signature = $property->getValue($command);

        expect($signature)->toContain('authz:snapshot');
        expect($signature)->toContain('action=list');
        expect($signature)->toContain('--name=');
        expect($signature)->toContain('--description=');
        expect($signature)->toContain('--from=');
        expect($signature)->toContain('--to=');
        expect($signature)->toContain('--snapshot=');
        expect($signature)->toContain('--dry-run');
        expect($signature)->toContain('--force');
    });

    it('has correct description', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('description');

        expect($property->getValue($command))->toBe('Manage permission snapshots');
    });

    it('extends Command', function (): void {
        expect(SnapshotCommand::class)->toExtend(Command::class);
    });

    it('has createSnapshot method', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('createSnapshot'))->toBeTrue();
        expect($reflection->getMethod('createSnapshot')->isProtected())->toBeTrue();
    });

    it('has listSnapshots method', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('listSnapshots'))->toBeTrue();
        expect($reflection->getMethod('listSnapshots')->isProtected())->toBeTrue();
    });

    it('has compareSnapshots method', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('compareSnapshots'))->toBeTrue();
        expect($reflection->getMethod('compareSnapshots')->isProtected())->toBeTrue();
    });

    it('has rollbackSnapshot method', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('rollbackSnapshot'))->toBeTrue();
        expect($reflection->getMethod('rollbackSnapshot')->isProtected())->toBeTrue();
    });

    it('has invalidAction method', function (): void {
        $command = new SnapshotCommand;
        $reflection = new \ReflectionClass($command);

        expect($reflection->hasMethod('invalidAction'))->toBeTrue();
        expect($reflection->getMethod('invalidAction')->isProtected())->toBeTrue();
    });
});
