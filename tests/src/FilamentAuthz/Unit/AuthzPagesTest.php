<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use Filament\Pages\Page;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('PermissionMatrixPage', function (): void {
    it('extends Page', function (): void {
        expect(is_subclass_of(PermissionMatrixPage::class, Page::class))->toBeTrue();
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Permission Matrix');
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('navigationLabel');

        expect($property->getDefaultValue())->toBe('Permission Matrix');
    });

    it('has correct navigation sort', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('navigationSort');

        expect($property->getDefaultValue())->toBe(10);
    });

    it('has selectedRole property', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('selectedRole');

        expect($property->isPublic())->toBeTrue();
    });

    it('has permissions property', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $property = $reflection->getProperty('permissions');

        expect($property->isPublic())->toBeTrue();
    });

    it('has getNavigationGroup method', function (): void {
        $group = PermissionMatrixPage::getNavigationGroup();

        // Uses config default which is 'Settings' in test environment
        expect($group)->toBeString();
    });

    it('can toggle permission state', function (): void {
        $page = new PermissionMatrixPage;
        $page->permissions = [];

        $page->togglePermission('perm-1');
        expect($page->permissions['perm-1'])->toBeTrue();

        $page->togglePermission('perm-1');
        expect($page->permissions['perm-1'])->toBeFalse();
    });

    it('returns null for selected role name when none selected', function (): void {
        $page = new PermissionMatrixPage;
        $page->selectedRole = null;

        expect($page->getSelectedRoleName())->toBeNull();
    });

    it('returns empty matrix when no grouped permissions', function (): void {
        $page = new PermissionMatrixPage;
        $page->groupedPermissions = [];
        $page->permissions = [];

        $matrix = $page->getMatrixData();

        expect($matrix)->toBe([]);
    });

    it('does nothing when saving without selected role', function (): void {
        $page = new PermissionMatrixPage;
        $page->selectedRole = null;

        // Should not throw exception
        $page->savePermissions();

        expect(true)->toBeTrue(); // If we get here, test passed
    });
});

describe('RoleHierarchyPage', function (): void {
    it('extends Page', function (): void {
        expect(is_subclass_of(RoleHierarchyPage::class, Page::class))->toBeTrue();
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyPage::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Role Hierarchy');
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyPage::class);
        $property = $reflection->getProperty('navigationLabel');

        expect($property->getDefaultValue())->toBe('Role Hierarchy');
    });

    it('has correct navigation sort', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyPage::class);
        $property = $reflection->getProperty('navigationSort');

        expect($property->getDefaultValue())->toBe(11);
    });

    it('has getNavigationGroup method', function (): void {
        $group = RoleHierarchyPage::getNavigationGroup();

        // Uses config default which is 'Settings' in test environment
        expect($group)->toBeString();
    });

    it('has hierarchyTree property', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyPage::class);
        $property = $reflection->getProperty('hierarchyTree');

        expect($property->isPublic())->toBeTrue();
    });

    it('has rootRoles property', function (): void {
        $reflection = new ReflectionClass(RoleHierarchyPage::class);
        $property = $reflection->getProperty('rootRoles');

        expect($property->isPublic())->toBeTrue();
    });

    it('has getHeaderActions method', function (): void {
        $method = new ReflectionMethod(RoleHierarchyPage::class, 'getHeaderActions');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('array');
    });

    it('has buildTree method', function (): void {
        $method = new ReflectionMethod(RoleHierarchyPage::class, 'buildTree');

        expect($method->isProtected())->toBeTrue();
    });

    it('has buildRoleNode method', function (): void {
        $method = new ReflectionMethod(RoleHierarchyPage::class, 'buildRoleNode');

        expect($method->isProtected())->toBeTrue();
    });
});
