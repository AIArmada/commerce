<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Concerns\HasAutoPermissions;
use AIArmada\FilamentAuthz\Contracts\RegistersPermissions;
use AIArmada\FilamentAuthz\Models\Permission;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test resource using HasAutoPermissions trait
 */
class TestAutoPermissionsResource extends Resource implements RegistersPermissions
{
    use HasAutoPermissions;

    protected static ?string $model = User::class;
}

/**
 * Test resource with custom permission key
 */
class CustomKeyResource extends Resource implements RegistersPermissions
{
    use HasAutoPermissions;

    protected static ?string $model = User::class;

    protected static ?string $permissionKey = 'custom_resource';
}

/**
 * Test resource with custom abilities
 */
class CustomAbilitiesResource extends Resource implements RegistersPermissions
{
    use HasAutoPermissions;

    protected static ?string $model = User::class;

    /** @var array<string> */
    protected static array $permissionAbilities = ['view', 'create', 'publish'];
}

/**
 * Test resource with custom group
 */
class CustomGroupResource extends Resource implements RegistersPermissions
{
    use HasAutoPermissions;

    protected static ?string $model = User::class;

    protected static ?string $permissionGroup = 'Admin';
}

/**
 * Test resource without wildcard
 */
class NoWildcardResource extends Resource implements RegistersPermissions
{
    use HasAutoPermissions;

    protected static ?string $model = User::class;

    protected static bool $registerWildcardPermission = false;
}

describe('HasAutoPermissions', function (): void {
    describe('getPermissionKey', function (): void {
        it('derives key from model name by default', function (): void {
            $key = TestAutoPermissionsResource::getPermissionKey();

            expect($key)->toBe('user');
        });

        it('uses custom permission key when set', function (): void {
            $key = CustomKeyResource::getPermissionKey();

            expect($key)->toBe('custom_resource');
        });
    });

    describe('getPermissionAbilities', function (): void {
        it('returns default CRUD abilities', function (): void {
            $abilities = TestAutoPermissionsResource::getPermissionAbilities();

            expect($abilities)->toBe([
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
                'deleteAny',
                'forceDelete',
                'forceDeleteAny',
                'restore',
                'restoreAny',
            ]);
        });

        it('uses custom abilities when set', function (): void {
            $abilities = CustomAbilitiesResource::getPermissionAbilities();

            expect($abilities)->toBe(['view', 'create', 'publish']);
        });
    });

    describe('getPermissionGroup', function (): void {
        it('returns null by default when no navigation group', function (): void {
            $group = TestAutoPermissionsResource::getPermissionGroup();

            expect($group)->toBeNull();
        });

        it('uses custom group when set', function (): void {
            $group = CustomGroupResource::getPermissionGroup();

            expect($group)->toBe('Admin');
        });
    });

    describe('shouldRegisterWildcard', function (): void {
        it('returns true by default', function (): void {
            expect(TestAutoPermissionsResource::shouldRegisterWildcard())->toBeTrue();
        });

        it('returns false when disabled', function (): void {
            expect(NoWildcardResource::shouldRegisterWildcard())->toBeFalse();
        });
    });

    describe('getPermissionNames', function (): void {
        it('returns full permission names with abilities', function (): void {
            $names = CustomAbilitiesResource::getPermissionNames();

            expect($names)->toContain('user.view', 'user.create', 'user.publish', 'user.*');
        });

        it('includes wildcard when enabled', function (): void {
            $names = TestAutoPermissionsResource::getPermissionNames();

            expect($names)->toContain('user.*');
        });

        it('excludes wildcard when disabled', function (): void {
            $names = NoWildcardResource::getPermissionNames();

            expect($names)->not->toContain('user.*');
        });
    });

    describe('canPerform', function (): void {
        it('returns false when no user authenticated', function (): void {
            expect(TestAutoPermissionsResource::canPerform('view'))->toBeFalse();
        });

        it('returns true when user has specific permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
            $user->givePermissionTo('user.view');

            $this->actingAs($user);

            expect(TestAutoPermissionsResource::canPerform('view'))->toBeTrue();
        });

        it('returns true when user has wildcard permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'user.*', 'guard_name' => 'web']);
            $user->givePermissionTo('user.*');

            $this->actingAs($user);

            expect(TestAutoPermissionsResource::canPerform('view'))->toBeTrue()
                ->and(TestAutoPermissionsResource::canPerform('update'))->toBeTrue()
                ->and(TestAutoPermissionsResource::canPerform('delete'))->toBeTrue();
        });

        it('returns false when user lacks permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $this->actingAs($user);

            expect(TestAutoPermissionsResource::canPerform('delete'))->toBeFalse();
        });
    });
});
