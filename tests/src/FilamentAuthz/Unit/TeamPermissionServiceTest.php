<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\TeamPermissionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Spatie\Permission\Models\Permission;

afterEach(function (): void {
    Mockery::close();
});

describe('TeamPermissionService', function (): void {
    describe('hasTeamPermission', function (): void {
        it('checks team permission via contextual auth service', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('canInTeam')
                ->with($user, 'edit-posts', 'team-123')
                ->once()
                ->andReturn(true);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->hasTeamPermission($user, 'edit-posts', 'team-123');

            expect($result)->toBeTrue();
        });

        it('returns false when user lacks team permission', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('canInTeam')
                ->with($user, 'edit-posts', 'team-456')
                ->once()
                ->andReturn(false);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->hasTeamPermission($user, 'edit-posts', 'team-456');

            expect($result)->toBeFalse();
        });
    });

    describe('grantTeamPermission', function (): void {
        it('grants team permission with all options', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $expiresAt = Carbon::now()->addDays(7);
            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) use ($user, $expiresAt) {
                    return $u === $user
                        && $perm === 'edit-posts'
                        && $scope === PermissionScope::Team
                        && $scopeVal === 'team-123'
                        && $cond === ['condition' => 'value']
                        && $exp === $expiresAt;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->grantTeamPermission(
                $user,
                'edit-posts',
                'team-123',
                ['condition' => 'value'],
                $expiresAt
            );

            expect($result)->toBe($scopedPermission);
        });

        it('grants team permission without expiry', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) {
                    return $perm === 'view-reports'
                        && $scope === PermissionScope::Team
                        && $scopeVal === 'team-999'
                        && $cond === []
                        && $exp === null;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->grantTeamPermission($user, 'view-reports', 'team-999');

            expect($result)->toBe($scopedPermission);
        });

        it('converts integer team id to string', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) {
                    return $scopeVal === '42';
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->grantTeamPermission($user, 'manage-team', 42);

            expect($result)->toBe($scopedPermission);
        });
    });

    describe('revokeTeamPermission', function (): void {
        it('revokes team permission', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('revokeScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal) use ($user) {
                    return $u === $user
                        && $perm === 'delete-posts'
                        && $scope === PermissionScope::Team
                        && $scopeVal === 'team-123';
                })
                ->once()
                ->andReturn(1);

            $service = new TeamPermissionService($contextualAuth);
            $result = $service->revokeTeamPermission($user, 'delete-posts', 'team-123');

            expect($result)->toBe(1);
        });
    });

    describe('getTeamPermissions', function (): void {
        it('returns empty collection when no permissions exist', function (): void {
            Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TeamPermissionService($contextualAuth);
            $permissions = $service->getTeamPermissions($user, 'team-123');

            expect($permissions)->toBeInstanceOf(Collection::class);
            expect($permissions)->toBeEmpty();
        });
    });

    describe('getTeamsWithPermission', function (): void {
        it('returns empty collection when no teams have permission', function (): void {
            Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TeamPermissionService($contextualAuth);
            $teams = $service->getTeamsWithPermission($user, 'edit-posts');

            expect($teams)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($teams)->toBeEmpty();
        });
    });

    describe('revokeAllTeamPermissions', function (): void {
        it('returns zero when no permissions to revoke', function (): void {
            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TeamPermissionService($contextualAuth);
            $count = $service->revokeAllTeamPermissions($user, 'team-123');

            expect($count)->toBe(0);
        });
    });

    describe('copyTeamPermissions', function (): void {
        it('returns zero when no permissions to copy', function (): void {
            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TeamPermissionService($contextualAuth);
            $count = $service->copyTeamPermissions($user, 'team-1', 'team-2');

            expect($count)->toBe(0);
        });
    });
});
