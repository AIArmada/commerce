<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
});

describe('RoleHierarchyWidget', function (): void {
    it('has correct sort order', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyWidget::class);
        $property = $reflection->getProperty('sort');

        expect($property->getValue())->toBe(2);
    });

    it('has full column span', function (): void {
        $widget = new RoleHierarchyWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');

        expect($property->getValue($widget))->toBe('full');
    });

    it('uses correct view', function (): void {
        $widget = new RoleHierarchyWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('view');

        expect($property->getValue($widget))->toBe('filament-authz::widgets.role-hierarchy');
    });

    it('returns empty array when no roles exist', function (): void {
        $mockService = Mockery::mock(RoleInheritanceService::class);
        $mockService->shouldReceive('getRootRoles')
            ->once()
            ->andReturn(new Collection);

        app()->instance(RoleInheritanceService::class, $mockService);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBe([]);
    });

    it('builds hierarchy with single root role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        // Give the role some permissions
        $perm1 = Spatie\Permission\Models\Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
        $perm2 = Spatie\Permission\Models\Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
        $role->givePermissionTo([$perm1, $perm2]);

        $mockService = Mockery::mock(RoleInheritanceService::class);
        $mockService->shouldReceive('getRootRoles')
            ->once()
            ->andReturn(new Collection([$role]));
        $mockService->shouldReceive('getChildren')
            ->with(Mockery::any())
            ->once()
            ->andReturn(new Collection);

        app()->instance(RoleInheritanceService::class, $mockService);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray()
            ->and(count($hierarchy))->toBe(1)
            ->and($hierarchy[0]['name'])->toBe('admin')
            ->and($hierarchy[0]['level'])->toBe(0)
            ->and($hierarchy[0]['permission_count'])->toBe(2)
            ->and($hierarchy[0]['children'])->toBe([]);
    });

    it('builds hierarchy with nested children', function (): void {
        // Create parent role with permissions
        $parentRole = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $perm1 = Spatie\Permission\Models\Permission::create(['name' => 'all.manage', 'guard_name' => 'web']);
        $perm2 = Spatie\Permission\Models\Permission::create(['name' => 'users.manage', 'guard_name' => 'web']);
        $parentRole->givePermissionTo([$perm1, $perm2]);

        // Create child role with permissions
        $childRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $perm3 = Spatie\Permission\Models\Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
        $childRole->givePermissionTo($perm3);

        $mockService = Mockery::mock(RoleInheritanceService::class);
        $mockService->shouldReceive('getRootRoles')
            ->once()
            ->andReturn(new Collection([$parentRole]));
        $mockService->shouldReceive('getChildren')
            ->with(Mockery::on(fn ($r) => $r->name === 'super-admin'))
            ->once()
            ->andReturn(new Collection([$childRole]));
        $mockService->shouldReceive('getChildren')
            ->with(Mockery::on(fn ($r) => $r->name === 'admin'))
            ->once()
            ->andReturn(new Collection);

        app()->instance(RoleInheritanceService::class, $mockService);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray()
            ->and(count($hierarchy))->toBe(1)
            ->and($hierarchy[0]['name'])->toBe('super-admin')
            ->and($hierarchy[0]['level'])->toBe(0)
            ->and($hierarchy[0]['permission_count'])->toBe(2)
            ->and(count($hierarchy[0]['children']))->toBe(1)
            ->and($hierarchy[0]['children'][0]['name'])->toBe('admin')
            ->and($hierarchy[0]['children'][0]['level'])->toBe(1)
            ->and($hierarchy[0]['children'][0]['permission_count'])->toBe(1);
    });

    it('handles multiple root roles', function (): void {
        // Create roles with permissions
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $perm1 = Spatie\Permission\Models\Permission::create(['name' => 'admin.manage', 'guard_name' => 'web']);
        $role1->givePermissionTo($perm1);

        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        // No permissions for editor

        $mockService = Mockery::mock(RoleInheritanceService::class);
        $mockService->shouldReceive('getRootRoles')
            ->once()
            ->andReturn(new Collection([$role1, $role2]));
        $mockService->shouldReceive('getChildren')
            ->andReturn(new Collection);

        app()->instance(RoleInheritanceService::class, $mockService);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray()
            ->and(count($hierarchy))->toBe(2)
            ->and($hierarchy[0]['name'])->toBe('admin')
            ->and($hierarchy[0]['permission_count'])->toBe(1)
            ->and($hierarchy[1]['name'])->toBe('editor')
            ->and($hierarchy[1]['permission_count'])->toBe(0);
    });
});
