<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->registry = new PermissionRegistry;
});

describe('PermissionRegistry', function (): void {
    describe('register', function (): void {
        it('registers a permission with name only', function (): void {
            $this->registry->register('posts.view');

            expect($this->registry->isRegistered('posts.view'))->toBeTrue();
        });

        it('registers a permission with all attributes', function (): void {
            $this->registry->register(
                'posts.view',
                'View posts',
                'Content',
                'posts'
            );

            $definition = $this->registry->getDefinition('posts.view');

            expect($definition)->toBe([
                'name' => 'posts.view',
                'description' => 'View posts',
                'group' => 'Content',
                'resource' => 'posts',
            ]);
        });

        it('returns self for chaining', function (): void {
            $result = $this->registry->register('posts.view');

            expect($result)->toBeInstanceOf(PermissionRegistry::class);
        });
    });

    describe('registerResource', function (): void {
        it('registers default CRUD permissions for a resource', function (): void {
            $this->registry->registerResource('posts');

            expect($this->registry->isRegistered('posts.viewAny'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.view'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.create'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.update'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.delete'))->toBeTrue();
        });

        it('registers custom abilities for a resource', function (): void {
            $this->registry->registerResource('posts', ['view', 'edit', 'publish']);

            expect($this->registry->isRegistered('posts.view'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.edit'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.publish'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.delete'))->toBeFalse();
        });

        it('registers with group', function (): void {
            $this->registry->registerResource('posts', ['view'], 'Content');

            $definition = $this->registry->getDefinition('posts.view');

            expect($definition['group'])->toBe('Content');
        });
    });

    describe('registerWildcard', function (): void {
        it('registers a wildcard permission', function (): void {
            $this->registry->registerWildcard('posts');

            expect($this->registry->isRegistered('posts.*'))->toBeTrue();
        });

        it('uses default description', function (): void {
            $this->registry->registerWildcard('posts');

            $definition = $this->registry->getDefinition('posts.*');

            expect($definition['description'])->toBe('All posts permissions');
        });

        it('uses custom description', function (): void {
            $this->registry->registerWildcard('posts', 'Full access to posts');

            $definition = $this->registry->getDefinition('posts.*');

            expect($definition['description'])->toBe('Full access to posts');
        });
    });

    describe('sync', function (): void {
        it('creates permissions in database', function (): void {
            $this->registry
                ->register('posts.view')
                ->register('posts.edit');

            $result = $this->registry->sync();

            expect($result)->toBe(['created' => 2, 'skipped' => 0])
                ->and(Permission::where('name', 'posts.view')->exists())->toBeTrue()
                ->and(Permission::where('name', 'posts.edit')->exists())->toBeTrue();
        });

        it('skips existing permissions', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $this->registry
                ->register('posts.view')
                ->register('posts.edit');

            $result = $this->registry->sync();

            expect($result)->toBe(['created' => 1, 'skipped' => 1]);
        });

        it('uses specified guard name', function (): void {
            $this->registry->register('api.access');

            $this->registry->sync('api');

            expect(Permission::where('name', 'api.access')->where('guard_name', 'api')->exists())->toBeTrue();
        });
    });

    describe('getDefinitions', function (): void {
        it('returns all registered definitions', function (): void {
            $this->registry
                ->register('posts.view')
                ->register('posts.edit');

            $definitions = $this->registry->getDefinitions();

            expect($definitions)->toHaveCount(2)
                ->and($definitions)->toHaveKeys(['posts.view', 'posts.edit']);
        });

        it('returns empty array when nothing registered', function (): void {
            expect($this->registry->getDefinitions())->toBeEmpty();
        });
    });

    describe('groupByResource', function (): void {
        it('groups definitions by resource', function (): void {
            $this->registry
                ->register('posts.view', null, null, 'posts')
                ->register('posts.edit', null, null, 'posts')
                ->register('users.view', null, null, 'users');

            $grouped = $this->registry->groupByResource();

            expect($grouped->has('posts'))->toBeTrue()
                ->and($grouped->has('users'))->toBeTrue()
                ->and($grouped->get('posts'))->toHaveCount(2)
                ->and($grouped->get('users'))->toHaveCount(1);
        });

        it('puts null resources in other group', function (): void {
            $this->registry->register('general.access');

            $grouped = $this->registry->groupByResource();

            expect($grouped->has('other'))->toBeTrue();
        });
    });

    describe('groupByGroup', function (): void {
        it('groups definitions by group', function (): void {
            $this->registry
                ->register('posts.view', null, 'Content', null)
                ->register('posts.edit', null, 'Content', null)
                ->register('users.view', null, 'Admin', null);

            $grouped = $this->registry->groupByGroup();

            expect($grouped->has('Content'))->toBeTrue()
                ->and($grouped->has('Admin'))->toBeTrue()
                ->and($grouped->get('Content'))->toHaveCount(2);
        });

        it('puts null groups in ungrouped', function (): void {
            $this->registry->register('general.access');

            $grouped = $this->registry->groupByGroup();

            expect($grouped->has('ungrouped'))->toBeTrue();
        });
    });

    describe('getResources', function (): void {
        it('returns unique resources', function (): void {
            $this->registry
                ->register('posts.view', null, null, 'posts')
                ->register('posts.edit', null, null, 'posts')
                ->register('users.view', null, null, 'users');

            $resources = $this->registry->getResources();

            expect($resources)->toHaveCount(2)
                ->and($resources->toArray())->toContain('posts')
                ->and($resources->toArray())->toContain('users');
        });

        it('filters out null resources', function (): void {
            $this->registry
                ->register('posts.view', null, null, 'posts')
                ->register('general.access');

            $resources = $this->registry->getResources();

            expect($resources)->toHaveCount(1)
                ->and($resources->toArray())->toContain('posts');
        });
    });

    describe('getGroups', function (): void {
        it('returns unique groups', function (): void {
            $this->registry
                ->register('posts.view', null, 'Content')
                ->register('posts.edit', null, 'Content')
                ->register('users.view', null, 'Admin');

            $groups = $this->registry->getGroups();

            expect($groups)->toHaveCount(2)
                ->and($groups->toArray())->toContain('Content')
                ->and($groups->toArray())->toContain('Admin');
        });
    });

    describe('isRegistered', function (): void {
        it('returns true for registered permission', function (): void {
            $this->registry->register('posts.view');

            expect($this->registry->isRegistered('posts.view'))->toBeTrue();
        });

        it('returns false for unregistered permission', function (): void {
            expect($this->registry->isRegistered('posts.view'))->toBeFalse();
        });
    });

    describe('getDefinition', function (): void {
        it('returns definition for registered permission', function (): void {
            $this->registry->register('posts.view', 'View posts');

            $definition = $this->registry->getDefinition('posts.view');

            expect($definition)->not->toBeNull()
                ->and($definition['description'])->toBe('View posts');
        });

        it('returns null for unregistered permission', function (): void {
            expect($this->registry->getDefinition('posts.view'))->toBeNull();
        });
    });

    describe('clear', function (): void {
        it('clears all registered definitions', function (): void {
            $this->registry
                ->register('posts.view')
                ->register('posts.edit');

            $this->registry->clear();

            expect($this->registry->getDefinitions())->toBeEmpty();
        });

        it('returns self for chaining', function (): void {
            $result = $this->registry->clear();

            expect($result)->toBeInstanceOf(PermissionRegistry::class);
        });
    });

    describe('loadFromConfig', function (): void {
        it('loads definitions from config array', function (): void {
            $config = [
                'posts.view' => [
                    'description' => 'View posts',
                    'group' => 'Content',
                    'resource' => 'posts',
                ],
                'posts.edit' => [
                    'description' => 'Edit posts',
                ],
            ];

            $this->registry->loadFromConfig($config);

            expect($this->registry->isRegistered('posts.view'))->toBeTrue()
                ->and($this->registry->isRegistered('posts.edit'))->toBeTrue();

            $definition = $this->registry->getDefinition('posts.view');
            expect($definition['description'])->toBe('View posts')
                ->and($definition['group'])->toBe('Content');
        });
    });

    describe('export', function (): void {
        it('exports all definitions', function (): void {
            $this->registry
                ->register('posts.view', 'View posts')
                ->register('posts.edit', 'Edit posts');

            $exported = $this->registry->export();

            expect($exported)->toHaveCount(2)
                ->and($exported)->toHaveKeys(['posts.view', 'posts.edit']);
        });
    });

    describe('getUnregisteredPermissions', function (): void {
        it('returns permissions in database but not in registry', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

            $this->registry->register('posts.view');

            $unregistered = $this->registry->getUnregisteredPermissions();

            expect($unregistered)->toHaveCount(2)
                ->and($unregistered->pluck('name')->toArray())->toContain('posts.edit')
                ->and($unregistered->pluck('name')->toArray())->toContain('posts.delete');
        });
    });

    describe('getMissingPermissions', function (): void {
        it('returns permissions in registry but not in database', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $this->registry
                ->register('posts.view')
                ->register('posts.edit')
                ->register('posts.delete');

            $missing = $this->registry->getMissingPermissions();

            expect($missing)->toHaveCount(2)
                ->and($missing->toArray())->toContain('posts.edit')
                ->and($missing->toArray())->toContain('posts.delete');
        });
    });
});
