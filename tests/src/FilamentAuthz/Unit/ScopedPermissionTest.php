<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.database.tables.scoped_permissions' => 'authz_scoped_permissions']);
    config(['auth.providers.users.model' => User::class]);
});

describe('ScopedPermission', function (): void {
    describe('create', function (): void {
        it('creates a scoped permission with basic attributes', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            expect($scopedPermission)
                ->permission_id->toBe($permission->id)
                ->permissionable_type->toBe($user->getMorphClass())
                ->permissionable_id->toBe($user->id)
                ->scope_type->toBe('global');
        });

        it('creates a scoped permission with team scope', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'team',
                'scope_id' => 'team-123',
                'granted_at' => now(),
            ]);

            expect($scopedPermission)
                ->scope_type->toBe('team')
                ->scope_id->toBe('team-123');
        });

        it('creates a scoped permission with expiration', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            expect($scopedPermission->expires_at)->not->toBeNull();
        });

        it('creates a scoped permission with conditions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'manage-posts', 'guard_name' => 'web']);

            $conditions = [
                ['attribute' => 'status', 'operator' => 'eq', 'value' => 'draft'],
            ];

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'conditions' => $conditions,
                'granted_at' => now(),
            ]);

            expect($scopedPermission->conditions)->toBe($conditions);
        });
    });

    describe('getTable', function (): void {
        it('returns table name from config', function (): void {
            $scopedPermission = new ScopedPermission;

            expect($scopedPermission->getTable())->toBe('authz_scoped_permissions');
        });

        it('returns custom table name from config', function (): void {
            config(['filament-authz.database.tables.scoped_permissions' => 'custom_scoped_permissions']);
            $scopedPermission = new ScopedPermission;

            expect($scopedPermission->getTable())->toBe('custom_scoped_permissions');
        });
    });

    describe('permission relationship', function (): void {
        it('belongs to a permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'view-users', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            expect($scopedPermission->permission)->toBeInstanceOf(Permission::class)
                ->and($scopedPermission->permission->id)->toBe($permission->id);
        });
    });

    describe('permissionable relationship', function (): void {
        it('morphs to the permissionable model', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'view-users', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            expect($scopedPermission->permissionable)->toBeInstanceOf(User::class)
                ->and($scopedPermission->permissionable->id)->toBe($user->id);
        });
    });

    describe('getScopeEnum', function (): void {
        it('returns Global scope for global type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'global']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Global);
        });

        it('returns Team scope for team type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'team']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Team);
        });

        it('returns Tenant scope for tenant type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'tenant']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Tenant);
        });

        it('returns Resource scope for resource type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'resource']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Resource);
        });

        it('returns Owner scope for owner type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'owner']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Owner);
        });

        it('returns Global for invalid scope type', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'invalid']);

            expect($scopedPermission->getScopeEnum())->toBe(PermissionScope::Global);
        });
    });

    describe('isExpired', function (): void {
        it('returns false when no expiration', function (): void {
            $scopedPermission = new ScopedPermission(['expires_at' => null]);

            expect($scopedPermission->isExpired())->toBeFalse();
        });

        it('returns false when expiration is in the future', function (): void {
            $scopedPermission = new ScopedPermission([
                'expires_at' => now()->addDays(30),
            ]);

            expect($scopedPermission->isExpired())->toBeFalse();
        });

        it('returns true when expiration is in the past', function (): void {
            $scopedPermission = new ScopedPermission([
                'expires_at' => now()->subDay(),
            ]);

            expect($scopedPermission->isExpired())->toBeTrue();
        });
    });

    describe('isActive', function (): void {
        it('returns true when not expired', function (): void {
            $scopedPermission = new ScopedPermission(['expires_at' => null]);

            expect($scopedPermission->isActive())->toBeTrue();
        });

        it('returns false when expired', function (): void {
            $scopedPermission = new ScopedPermission([
                'expires_at' => now()->subDay(),
            ]);

            expect($scopedPermission->isActive())->toBeFalse();
        });
    });

    describe('matchesContext', function (): void {
        it('returns true for global scope', function (): void {
            $scopedPermission = new ScopedPermission(['scope_type' => 'global']);

            expect($scopedPermission->matchesContext(['team_id' => 'team-123']))->toBeTrue();
        });

        it('returns true for matching team context', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'team',
                'scope_id' => 'team-123',
            ]);

            expect($scopedPermission->matchesContext(['team_id' => 'team-123']))->toBeTrue();
        });

        it('returns false for non-matching team context', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'team',
                'scope_id' => 'team-123',
            ]);

            expect($scopedPermission->matchesContext(['team_id' => 'team-456']))->toBeFalse();
        });

        it('returns false when context key is missing', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'team',
                'scope_id' => 'team-123',
            ]);

            expect($scopedPermission->matchesContext([]))->toBeFalse();
        });

        it('returns true for matching tenant context', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'tenant',
                'scope_id' => 'tenant-456',
            ]);

            expect($scopedPermission->matchesContext(['tenant_id' => 'tenant-456']))->toBeTrue();
        });

        it('returns true for matching resource context', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'resource',
                'scope_id' => 'resource-789',
            ]);

            expect($scopedPermission->matchesContext(['resource_id' => 'resource-789']))->toBeTrue();
        });

        it('returns true for matching owner context', function (): void {
            $scopedPermission = new ScopedPermission([
                'scope_type' => 'owner',
                'scope_id' => 'owner-111',
            ]);

            expect($scopedPermission->matchesContext(['record_owner_id' => 'owner-111']))->toBeTrue();
        });
    });

    describe('evaluateConditions', function (): void {
        it('returns true when no conditions', function (): void {
            $scopedPermission = new ScopedPermission(['conditions' => null]);

            expect($scopedPermission->evaluateConditions(['status' => 'draft']))->toBeTrue();
        });

        it('returns true when conditions are empty array', function (): void {
            $scopedPermission = new ScopedPermission(['conditions' => []]);

            expect($scopedPermission->evaluateConditions(['status' => 'draft']))->toBeTrue();
        });

        it('evaluates eq condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'eq', 'value' => 'draft'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['status' => 'draft']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['status' => 'published']))->toBeFalse();
        });

        it('evaluates neq condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'neq', 'value' => 'deleted'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['status' => 'active']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['status' => 'deleted']))->toBeFalse();
        });

        it('evaluates gt condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'age', 'operator' => 'gt', 'value' => 18],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['age' => 25]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['age' => 18]))->toBeFalse();
        });

        it('evaluates gte condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'age', 'operator' => 'gte', 'value' => 18],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['age' => 18]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['age' => 17]))->toBeFalse();
        });

        it('evaluates lt condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'price', 'operator' => 'lt', 'value' => 100],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['price' => 50]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['price' => 100]))->toBeFalse();
        });

        it('evaluates lte condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'price', 'operator' => 'lte', 'value' => 100],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['price' => 100]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['price' => 101]))->toBeFalse();
        });

        it('evaluates in condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'in', 'value' => ['active', 'pending']],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['status' => 'active']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['status' => 'deleted']))->toBeFalse();
        });

        it('evaluates not_in condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'not_in', 'value' => ['deleted', 'banned']],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['status' => 'active']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['status' => 'deleted']))->toBeFalse();
        });

        it('evaluates contains condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'email', 'operator' => 'contains', 'value' => '@example.com'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['email' => 'user@example.com']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['email' => 'user@other.com']))->toBeFalse();
        });

        it('evaluates is_null condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'deleted_at', 'operator' => 'is_null'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['deleted_at' => null]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['deleted_at' => '2024-01-01']))->toBeFalse();
        });

        it('evaluates is_not_null condition', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'verified_at', 'operator' => 'is_not_null'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['verified_at' => '2024-01-01']))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['verified_at' => null]))->toBeFalse();
        });

        it('evaluates multiple conditions with AND logic', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'eq', 'value' => 'active'],
                    ['attribute' => 'age', 'operator' => 'gte', 'value' => 18],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['status' => 'active', 'age' => 25]))->toBeTrue()
                ->and($scopedPermission->evaluateConditions(['status' => 'active', 'age' => 17]))->toBeFalse()
                ->and($scopedPermission->evaluateConditions(['status' => 'inactive', 'age' => 25]))->toBeFalse();
        });

        it('returns true for missing attribute with no operator', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['operator' => 'eq', 'value' => 'test'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['anything' => 'value']))->toBeTrue();
        });

        it('returns true for unknown operator', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'field', 'operator' => 'unknown', 'value' => 'test'],
                ],
            ]);

            expect($scopedPermission->evaluateConditions(['field' => 'test']))->toBeTrue();
        });
    });

    describe('matchesConditions (alias)', function (): void {
        it('is an alias for evaluateConditions', function (): void {
            $scopedPermission = new ScopedPermission([
                'conditions' => [
                    ['attribute' => 'status', 'operator' => 'eq', 'value' => 'active'],
                ],
            ]);

            expect($scopedPermission->matchesConditions(['status' => 'active']))->toBeTrue()
                ->and($scopedPermission->matchesConditions(['status' => 'inactive']))->toBeFalse();
        });
    });

    describe('scopeActive', function (): void {
        it('filters to active (non-expired) permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => null,
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now()->subDays(30),
                'expires_at' => now()->subDay(),
            ]);

            $active = ScopedPermission::active()->get();

            expect($active)->toHaveCount(2);
        });
    });

    describe('scopeExpired', function (): void {
        it('filters to expired permissions only', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => null,
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now()->subDays(60),
                'expires_at' => now()->subDay(),
            ]);

            $expired = ScopedPermission::expired()->get();

            expect($expired)->toHaveCount(1);
        });
    });

    describe('scopeOfType', function (): void {
        it('filters by scope type string', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'team',
                'scope_id' => 'team-123',
                'granted_at' => now(),
            ]);

            $teamScoped = ScopedPermission::ofType('team')->get();

            expect($teamScoped)->toHaveCount(1)
                ->and($teamScoped->first()->scope_type)->toBe('team');
        });

        it('filters by scope type enum', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'tenant',
                'scope_id' => 'tenant-456',
                'granted_at' => now(),
            ]);

            $tenantScoped = ScopedPermission::ofType(PermissionScope::Tenant)->get();

            expect($tenantScoped)->toHaveCount(1);
        });
    });

    describe('scopeForScope', function (): void {
        it('filters by scope type and id', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'team',
                'scope_id' => 'team-123',
                'granted_at' => now(),
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'team',
                'scope_id' => 'team-456',
                'granted_at' => now(),
            ]);

            $team123 = ScopedPermission::forScope('team', 'team-123')->get();

            expect($team123)->toHaveCount(1)
                ->and($team123->first()->scope_id)->toBe('team-123');
        });
    });

    describe('scopeForPermissionable', function (): void {
        it('filters by permissionable model', function (): void {
            $user1 = User::create([
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password',
            ]);

            $user2 = User::create([
                'name' => 'User 2',
                'email' => 'user2@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user1->getMorphClass(),
                'permissionable_id' => $user1->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user2->getMorphClass(),
                'permissionable_id' => $user2->id,
                'scope_type' => 'global',
                'granted_at' => now(),
            ]);

            $user1Perms = ScopedPermission::forPermissionable($user1)->get();

            expect($user1Perms)->toHaveCount(1)
                ->and($user1Perms->first()->permissionable_id)->toBe((string) $user1->id);
        });
    });

    describe('scopeExpiringWithin', function (): void {
        it('filters permissions expiring within given days', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            // No expiration
            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => null,
            ]);

            // Expires in 5 days
            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);

            // Expires in 15 days
            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => now()->addDays(15),
            ]);

            // Already expired
            ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now()->subDays(30),
                'expires_at' => now()->subDay(),
            ]);

            $expiringWithin7Days = ScopedPermission::expiringWithin(7)->get();

            expect($expiringWithin7Days)->toHaveCount(1);
        });
    });

    describe('casts', function (): void {
        it('casts conditions to array', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'conditions' => [['attribute' => 'status', 'operator' => 'eq', 'value' => 'active']],
                'granted_at' => now(),
            ]);

            $scopedPermission->refresh();

            expect($scopedPermission->conditions)->toBeArray();
        });

        it('casts granted_at to datetime', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => '2024-01-15 10:30:00',
            ]);

            $scopedPermission->refresh();

            expect($scopedPermission->granted_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        });

        it('casts expires_at to datetime', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $permission = Permission::create(['name' => 'test-perm', 'guard_name' => 'web']);

            $scopedPermission = ScopedPermission::create([
                'permission_id' => $permission->id,
                'permissionable_type' => $user->getMorphClass(),
                'permissionable_id' => $user->id,
                'scope_type' => 'global',
                'granted_at' => now(),
                'expires_at' => '2024-12-31 23:59:59',
            ]);

            $scopedPermission->refresh();

            expect($scopedPermission->expires_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        });
    });
});
