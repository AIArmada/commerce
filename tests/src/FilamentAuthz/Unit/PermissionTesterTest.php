<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionTester;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->aggregator = Mockery::mock(PermissionAggregator::class);
    $this->policyEngine = Mockery::mock(PolicyEngine::class);
    $this->contextualAuth = Mockery::mock(ContextualAuthorizationService::class);

    $this->tester = new PermissionTester(
        $this->aggregator,
        $this->policyEngine,
        $this->contextualAuth
    );

    $this->user = new class
    {
        public function getKey(): string
        {
            return 'user-1';
        }
    };
});

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionTester', function (): void {
    describe('test', function (): void {
        it('returns allowed when user has permission directly', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.view')
                ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

            $result = $this->tester->test($this->user, 'posts.view');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe('Granted directly to user')
                ->and($result['source']['type'])->toBe('direct');
        });

        it('returns allowed when user has permission via role', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.edit')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.edit')
                ->andReturn(['type' => 'role', 'source' => 'editor', 'via' => null]);

            $result = $this->tester->test($this->user, 'posts.edit');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe("Granted via role 'editor'")
                ->and($result['source']['type'])->toBe('role');
        });

        it('returns allowed when user has inherited permission', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.delete')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.delete')
                ->andReturn(['type' => 'inherited', 'source' => 'admin', 'via' => 'manager']);

            $result = $this->tester->test($this->user, 'posts.delete');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe("Inherited from role 'admin' via 'manager'");
        });

        it('checks contextual permissions when context provided', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(false);

            $this->contextualAuth
                ->shouldReceive('canWithContext')
                ->with($this->user, 'posts.view', ['team_id' => 'team-1'])
                ->andReturn(true);

            $result = $this->tester->test($this->user, 'posts.view', ['team_id' => 'team-1']);

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe('Granted via contextual/scoped permission')
                ->and($result['source']['type'])->toBe('contextual');
        });

        it('checks ABAC policies when no permission found', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(false);

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('view', 'posts', [])
                ->andReturn(PolicyDecision::Permit);

            $result = $this->tester->test($this->user, 'posts.view');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe('Granted via ABAC policy')
                ->and($result['policy_decision'])->toBe(PolicyDecision::Permit);
        });

        it('returns not allowed when no authorization found', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.delete')
                ->andReturn(false);

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('delete', 'posts', [])
                ->andReturn(PolicyDecision::Deny);

            $result = $this->tester->test($this->user, 'posts.delete');

            expect($result['allowed'])->toBeFalse()
                ->and($result['reason'])->toBe('Permission not found through any authorization mechanism')
                ->and($result['policy_decision'])->toBe(PolicyDecision::Deny);
        });

        it('handles wildcard permissions', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.*')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.*')
                ->andReturn(['type' => 'wildcard', 'source' => 'posts.*', 'via' => null]);

            $result = $this->tester->test($this->user, 'posts.*');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe("Matched wildcard permission 'posts.*'");
        });

        it('handles implicit permissions', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.view')
                ->andReturn(['type' => 'implicit', 'source' => 'posts.manage', 'via' => null]);

            $result = $this->tester->test($this->user, 'posts.view');

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe("Implied by permission 'posts.manage'");
        });
    });

    describe('testBatch', function (): void {
        it('tests multiple permissions at once', function (): void {
            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.view')
                ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.delete')
                ->andReturn(false);

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('delete', 'posts', [])
                ->andReturn(PolicyDecision::Deny);

            $results = $this->tester->testBatch($this->user, ['posts.view', 'posts.delete']);

            expect($results)->toHaveKeys(['posts.view', 'posts.delete'])
                ->and($results['posts.view']['allowed'])->toBeTrue()
                ->and($results['posts.delete']['allowed'])->toBeFalse();
        });
    });

    describe('simulateRoleGrant', function (): void {
        it('simulates granting a role', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $perm1 = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $perm2 = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo([$perm1, $perm2]);

            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.view']));

            $result = $this->tester->simulateRoleGrant($this->user, $role);

            expect($result['current_permissions'])->toBe(['posts.view'])
                ->and($result['new_permissions'])->toBe(['posts.edit'])
                ->and($result['removed_permissions'])->toBe([])
                ->and($result['net_change'])->toBe(1);
        });

        it('returns empty new permissions if user already has all', function (): void {
            $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
            $perm = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $role->givePermissionTo($perm);

            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.view', 'posts.edit']));

            $result = $this->tester->simulateRoleGrant($this->user, $role);

            expect($result['new_permissions'])->toBe([])
                ->and($result['net_change'])->toBe(0);
        });
    });

    describe('simulateRoleRevoke', function (): void {
        it('simulates revoking a role', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $perm = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo($perm);

            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.view', 'posts.edit']));

            $this->aggregator
                ->shouldReceive('getEffectiveRoles')
                ->with($this->user)
                ->andReturn(new Collection([$role]));

            $result = $this->tester->simulateRoleRevoke($this->user, $role);

            expect($result['current_permissions'])->toBe(['posts.view', 'posts.edit'])
                ->and($result['removed_permissions'])->toBe(['posts.edit'])
                ->and($result['net_change'])->toBe(-1);
        });

        it('does not remove permissions provided by other roles', function (): void {
            $role1 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $role2 = Role::create(['name' => 'writer', 'guard_name' => 'web']);
            $perm = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role1->givePermissionTo($perm);
            $role2->givePermissionTo($perm);

            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.edit']));

            $this->aggregator
                ->shouldReceive('getEffectiveRoles')
                ->with($this->user)
                ->andReturn(new Collection([$role1, $role2]));

            $result = $this->tester->simulateRoleRevoke($this->user, $role1);

            expect($result['removed_permissions'])->toBe([])
                ->and($result['net_change'])->toBe(0);
        });
    });

    describe('generatePermissionMatrix', function (): void {
        it('generates permission matrix for user', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.view')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.view')
                ->andReturn(['type' => 'role', 'source' => 'editor', 'via' => null]);

            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.edit')
                ->andReturn(false);

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('edit', 'posts', [])
                ->andReturn(PolicyDecision::Deny);

            $matrix = $this->tester->generatePermissionMatrix($this->user);

            expect($matrix)->toHaveKeys(['posts.view', 'posts.edit'])
                ->and($matrix['posts.view']['has_permission'])->toBeTrue()
                ->and($matrix['posts.view']['source'])->toBe('editor')
                ->and($matrix['posts.edit']['has_permission'])->toBeFalse();
        });

        it('identifies inherited permissions', function (): void {
            Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);

            $this->aggregator
                ->shouldReceive('userHasPermission')
                ->with($this->user, 'posts.manage')
                ->andReturn(true);

            $this->aggregator
                ->shouldReceive('getPermissionSource')
                ->with($this->user, 'posts.manage')
                ->andReturn(['type' => 'inherited', 'source' => 'admin', 'via' => 'manager']);

            $matrix = $this->tester->generatePermissionMatrix($this->user);

            expect($matrix['posts.manage']['inherited'])->toBeTrue();
        });
    });

    describe('detectConflicts', function (): void {
        it('detects policy override conflicts', function (): void {
            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.delete']));

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('delete', 'posts', [])
                ->andReturn(PolicyDecision::Deny);

            $conflicts = $this->tester->detectConflicts($this->user);

            expect($conflicts)->toHaveCount(1)
                ->and($conflicts[0]['permission'])->toBe('posts.delete')
                ->and($conflicts[0]['conflict_type'])->toBe('policy_override')
                ->and($conflicts[0]['details'])->toContain('ABAC policy denies');
        });

        it('returns empty array when no conflicts', function (): void {
            $this->aggregator
                ->shouldReceive('getEffectivePermissionNames')
                ->with($this->user)
                ->andReturn(collect(['posts.view']));

            $this->policyEngine
                ->shouldReceive('evaluate')
                ->with('view', 'posts', [])
                ->andReturn(PolicyDecision::Permit);

            $conflicts = $this->tester->detectConflicts($this->user);

            expect($conflicts)->toBe([]);
        });
    });
});
