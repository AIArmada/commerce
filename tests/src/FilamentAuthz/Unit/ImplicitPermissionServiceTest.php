<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new ImplicitPermissionService;
});

describe('ImplicitPermissionService', function (): void {
    describe('expand', function (): void {
        it('expands permission with implicit abilities', function (): void {
            $expanded = $this->service->expand('posts.manage');

            expect($expanded->toArray())->toContain(
                'posts.viewAny',
                'posts.view',
                'posts.create',
                'posts.update',
                'posts.delete'
            );
        });

        it('returns original permission when no expansion exists', function (): void {
            $expanded = $this->service->expand('posts.view');

            expect($expanded->toArray())->toBe(['posts.view']);
        });

        it('returns original for single part permission', function (): void {
            $expanded = $this->service->expand('admin');

            expect($expanded->toArray())->toBe(['admin']);
        });

        it('expands edit to view and update', function (): void {
            $expanded = $this->service->expand('posts.edit');

            expect($expanded->toArray())->toContain('posts.view', 'posts.update');
        });

        it('expands admin to all CRUD plus replicate', function (): void {
            $expanded = $this->service->expand('posts.admin');

            expect($expanded->toArray())->toContain(
                'posts.viewAny',
                'posts.view',
                'posts.create',
                'posts.update',
                'posts.delete',
                'posts.replicate'
            );
        });
    });

    describe('getImplicitAbilities', function (): void {
        it('returns implicit abilities for manage', function (): void {
            $abilities = $this->service->getImplicitAbilities('manage');

            expect($abilities->toArray())->toBe(['viewAny', 'view', 'create', 'update', 'delete']);
        });

        it('returns implicit abilities for edit', function (): void {
            $abilities = $this->service->getImplicitAbilities('edit');

            expect($abilities->toArray())->toBe(['view', 'update']);
        });

        it('returns empty for unknown ability', function (): void {
            $abilities = $this->service->getImplicitAbilities('unknown');

            expect($abilities)->toBeEmpty();
        });
    });

    describe('implies', function (): void {
        it('returns true for same permission', function (): void {
            expect($this->service->implies('posts.view', 'posts.view'))->toBeTrue();
        });

        it('returns true when permission implies another', function (): void {
            expect($this->service->implies('posts.manage', 'posts.view'))->toBeTrue()
                ->and($this->service->implies('posts.manage', 'posts.create'))->toBeTrue()
                ->and($this->service->implies('posts.manage', 'posts.delete'))->toBeTrue();
        });

        it('returns false for different resources', function (): void {
            expect($this->service->implies('posts.manage', 'users.view'))->toBeFalse();
        });

        it('returns false when no implication exists', function (): void {
            expect($this->service->implies('posts.view', 'posts.delete'))->toBeFalse();
        });

        it('returns false for single part permissions', function (): void {
            expect($this->service->implies('admin', 'posts.view'))->toBeFalse();
        });

        it('handles full_access wildcard', function (): void {
            expect($this->service->implies('posts.full_access', 'posts.anything'))->toBeTrue();
        });
    });

    describe('registerMapping', function (): void {
        it('registers custom mapping', function (): void {
            $this->service->registerMapping('superadmin', ['view', 'edit', 'delete', 'manage', 'admin']);

            $abilities = $this->service->getImplicitAbilities('superadmin');

            expect($abilities->toArray())->toBe(['view', 'edit', 'delete', 'manage', 'admin']);
        });

        it('clears cache after registration', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:implicit_map');

            $this->service->registerMapping('test', ['view']);
        });
    });

    describe('registerMappings', function (): void {
        it('registers multiple mappings', function (): void {
            $this->service->registerMappings([
                'custom1' => ['a', 'b'],
                'custom2' => ['c', 'd'],
            ]);

            expect($this->service->getImplicitAbilities('custom1')->toArray())->toBe(['a', 'b'])
                ->and($this->service->getImplicitAbilities('custom2')->toArray())->toBe(['c', 'd']);
        });
    });

    describe('getAllMappings', function (): void {
        it('returns all standard mappings', function (): void {
            $mappings = $this->service->getAllMappings();

            expect($mappings)->toHaveKeys(['manage', 'edit', 'admin', 'full_access']);
        });

        it('includes group-defined mappings', function (): void {
            PermissionGroup::create([
                'name' => 'Custom Group',
                'slug' => 'custom-group',
                'implicit_abilities' => [
                    'supervisor' => ['view', 'approve'],
                ],
            ]);

            Cache::flush();

            $mappings = $this->service->getAllMappings();

            expect($mappings)->toHaveKey('supervisor')
                ->and($mappings['supervisor'])->toContain('view', 'approve');
        });

        it('uses caching', function (): void {
            Cache::shouldReceive('remember')
                ->once()
                ->andReturn(['manage' => ['view']]);

            $this->service->getAllMappings();
        });
    });

    describe('expandUserPermissions', function (): void {
        it('expands all user permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.manage');

            $expanded = $this->service->expandUserPermissions($user);

            expect($expanded->toArray())->toContain(
                'posts.viewAny',
                'posts.view',
                'posts.create',
                'posts.update',
                'posts.delete'
            );
        });

        it('returns empty for users without getAllPermissions method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $expanded = $this->service->expandUserPermissions($nonUser);

            expect($expanded)->toBeEmpty();
        });

        it('deduplicates expanded permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $user->givePermissionTo(['posts.manage', 'posts.view']);

            $expanded = $this->service->expandUserPermissions($user);

            // posts.view should only appear once
            $viewCount = $expanded->filter(fn ($p) => $p === 'posts.view')->count();
            expect($viewCount)->toBe(1);
        });
    });

    describe('userHasPermission', function (): void {
        it('returns true for direct permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.view');

            expect($this->service->userHasPermission($user, 'posts.view'))->toBeTrue();
        });

        it('returns true for implied permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.manage');

            expect($this->service->userHasPermission($user, 'posts.view'))->toBeTrue()
                ->and($this->service->userHasPermission($user, 'posts.create'))->toBeTrue();
        });

        it('returns false for unrelated permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.view');

            expect($this->service->userHasPermission($user, 'users.view'))->toBeFalse();
        });

        it('returns false for users without getAllPermissions method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            expect($this->service->userHasPermission($nonUser, 'any.permission'))->toBeFalse();
        });
    });

    describe('clearCache', function (): void {
        it('clears the implicit map cache', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:implicit_map');

            $this->service->clearCache();
        });
    });
});
