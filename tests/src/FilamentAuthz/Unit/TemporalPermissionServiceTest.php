<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\TemporalPermissionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Spatie\Permission\Models\Permission;

afterEach(function (): void {
    Mockery::close();
});

describe('TemporalPermissionService', function (): void {
    describe('grantTemporaryPermission', function (): void {
        it('grants temporary permission with default scope', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $expiresAt = Carbon::now()->addHours(2);
            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) use ($expiresAt) {
                    return $perm === 'edit-posts'
                        && $scope === PermissionScope::Temporal
                        && $scopeVal === 'temporary'
                        && $cond === []
                        && $exp === $expiresAt;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantTemporaryPermission($user, 'edit-posts', $expiresAt);

            expect($result)->toBe($scopedPermission);
        });

        it('grants temporary permission with custom scope', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $expiresAt = Carbon::now()->addDays(1);
            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) {
                    return $scope === PermissionScope::Team
                        && $scopeVal === 'team-123'
                        && $cond === ['reason' => 'emergency'];
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantTemporaryPermission(
                $user,
                'admin-access',
                $expiresAt,
                PermissionScope::Team,
                'team-123',
                ['reason' => 'emergency']
            );

            expect($result)->toBe($scopedPermission);
        });
    });

    describe('grantForDuration', function (): void {
        it('grants permission for specified minutes', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $scopedPermission = Mockery::mock(ScopedPermission::class);

            Carbon::setTestNow(Carbon::create(2024, 1, 15, 10, 0, 0));

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) {
                    return $perm === 'emergency-access'
                        && $scope === PermissionScope::Temporal
                        && $scopeVal === 'temporary'
                        && $exp->format('Y-m-d H:i:s') === '2024-01-15 10:30:00';
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantForDuration($user, 'emergency-access', 30);

            expect($result)->toBe($scopedPermission);

            Carbon::setTestNow();
        });

        it('grants permission with custom scope for duration', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $scopedPermission = Mockery::mock(ScopedPermission::class);

            Carbon::setTestNow(Carbon::create(2024, 1, 15, 10, 0, 0));

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) {
                    return $scope === PermissionScope::Team
                        && $scopeVal === 'team-999';
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantForDuration(
                $user,
                'team-admin',
                60,
                PermissionScope::Team,
                'team-999'
            );

            expect($result)->toBe($scopedPermission);

            Carbon::setTestNow();
        });
    });

    describe('grantDuringHours', function (): void {
        it('grants permission valid during specific hours', function (): void {
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
                    return $perm === 'office-access'
                        && $scope === PermissionScope::Temporal
                        && $scopeVal === 'hours:9-17'
                        && $cond === [
                            'time_range' => [
                                'start_hour' => 9,
                                'end_hour' => 17,
                            ],
                        ]
                        && $exp === null;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantDuringHours($user, 'office-access', 9, 17);

            expect($result)->toBe($scopedPermission);
        });

        it('grants permission during hours with expiration', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $expiresAt = Carbon::now()->addWeek();
            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) use ($expiresAt) {
                    return $scopeVal === 'hours:22-6'
                        && $exp === $expiresAt;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantDuringHours($user, 'night-shift', 22, 6, $expiresAt);

            expect($result)->toBe($scopedPermission);
        });
    });

    describe('grantOnDays', function (): void {
        it('grants permission valid on specific days', function (): void {
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
                    return $perm === 'weekend-access'
                        && $scope === PermissionScope::Temporal
                        && $scopeVal === 'days:0,6'
                        && $cond === [
                            'allowed_days' => [0, 6],
                        ]
                        && $exp === null;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantOnDays($user, 'weekend-access', [0, 6]); // Sunday, Saturday

            expect($result)->toBe($scopedPermission);
        });

        it('grants permission on days with expiration', function (): void {
            $user = new class
            {
                public function getKey(): int
                {
                    return 1;
                }
            };

            $expiresAt = Carbon::now()->addMonth();
            $scopedPermission = Mockery::mock(ScopedPermission::class);

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $contextualAuth->shouldReceive('grantScopedPermission')
                ->withArgs(function ($u, $perm, $scope, $scopeVal, $cond, $exp) use ($expiresAt) {
                    return $scopeVal === 'days:1,2,3,4,5'
                        && $cond === [
                            'allowed_days' => [1, 2, 3, 4, 5],
                        ]
                        && $exp === $expiresAt;
                })
                ->once()
                ->andReturn($scopedPermission);

            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->grantOnDays($user, 'weekday-access', [1, 2, 3, 4, 5], $expiresAt);

            expect($result)->toBe($scopedPermission);
        });
    });

    describe('hasActiveTemporaryPermission', function (): void {
        it('returns false when no permissions exist', function (): void {
            Permission::create(['name' => 'temp-access', 'guard_name' => 'web']);

            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->hasActiveTemporaryPermission($user, 'temp-access');

            expect($result)->toBeFalse();
        });
    });

    describe('getExpiringPermissions', function (): void {
        it('returns empty collection when no permissions expiring', function (): void {
            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->getExpiringPermissions($user, 60);

            expect($result)->toBeInstanceOf(Collection::class);
            expect($result)->toBeEmpty();
        });

        it('uses custom timeframe for expiring permissions', function (): void {
            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->getExpiringPermissions($user, 120);

            expect($result)->toBeEmpty();
        });
    });

    describe('extendPermission', function (): void {
        it('returns null when no permission exists to extend', function (): void {
            Permission::create(['name' => 'extend-test', 'guard_name' => 'web']);

            $user = new class
            {
                public function getKey(): string
                {
                    return 'user-uuid';
                }
            };

            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->extendPermission($user, 'extend-test', 30);

            expect($result)->toBeNull();
        });
    });

    describe('revokeExpired', function (): void {
        it('returns zero when no expired permissions', function (): void {
            $contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
            $service = new TemporalPermissionService($contextualAuth);
            $result = $service->revokeExpired();

            expect($result)->toBe(0);
        });
    });
});
