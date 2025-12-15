<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.database.tables.role_templates' => 'authz_role_templates']);
    config(['filament-authz.owner.enabled' => false]);
    config(['auth.providers.users.model' => User::class]);
});

describe('RoleTemplate', function (): void {
    describe('create', function (): void {
        it('creates a role template with basic attributes', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Manager Template',
                'slug' => 'manager-template',
                'guard_name' => 'web',
            ]);

            expect($template)
                ->name->toBe('Manager Template')
                ->slug->toBe('manager-template')
                ->guard_name->toBe('web');
        });

        it('creates a role template with description', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Admin Template',
                'slug' => 'admin-template',
                'description' => 'Template for admin roles',
                'guard_name' => 'web',
            ]);

            expect($template->description)->toBe('Template for admin roles');
        });

        it('creates a role template with default permissions', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Editor Template',
                'slug' => 'editor-template',
                'guard_name' => 'web',
                'default_permissions' => ['posts.view', 'posts.create', 'posts.edit'],
            ]);

            expect($template->default_permissions)->toBe(['posts.view', 'posts.create', 'posts.edit']);
        });

        it('creates a role template with metadata', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Custom Template',
                'slug' => 'custom-template',
                'guard_name' => 'web',
                'metadata' => ['color' => 'blue', 'icon' => 'user'],
            ]);

            expect($template->metadata)->toBe(['color' => 'blue', 'icon' => 'user']);
        });

        it('creates a system role template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'System Template',
                'slug' => 'system-template',
                'guard_name' => 'web',
                'is_system' => true,
            ]);

            expect($template->is_system)->toBeTrue();
        });

        it('creates an inactive role template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Inactive Template',
                'slug' => 'inactive-template',
                'guard_name' => 'web',
                'is_active' => false,
            ]);

            expect($template->is_active)->toBeFalse();
        });
    });

    describe('getTable', function (): void {
        it('returns table name from config', function (): void {
            $template = new RoleTemplate;

            expect($template->getTable())->toBe('authz_role_templates');
        });

        it('returns custom table name from config', function (): void {
            config(['filament-authz.database.tables.role_templates' => 'custom_role_templates']);
            $template = new RoleTemplate;

            expect($template->getTable())->toBe('custom_role_templates');
        });
    });

    describe('parent relationship', function (): void {
        it('belongs to a parent template', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent Template',
                'slug' => 'parent-template',
                'guard_name' => 'web',
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child Template',
                'slug' => 'child-template',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            expect($child->parent)->toBeInstanceOf(RoleTemplate::class)
                ->and($child->parent->id)->toBe($parent->id);
        });

        it('returns null when no parent', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Root Template',
                'slug' => 'root-template',
                'guard_name' => 'web',
            ]);

            expect($template->parent)->toBeNull();
        });
    });

    describe('children relationship', function (): void {
        it('has many children templates', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent Template',
                'slug' => 'parent-template',
                'guard_name' => 'web',
            ]);

            RoleTemplate::create([
                'name' => 'Child 1',
                'slug' => 'child-1',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            RoleTemplate::create([
                'name' => 'Child 2',
                'slug' => 'child-2',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            expect($parent->children)->toHaveCount(2);
        });
    });

    describe('getAllDefaultPermissions', function (): void {
        it('returns own permissions when no parent', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Template',
                'slug' => 'template',
                'guard_name' => 'web',
                'default_permissions' => ['users.view', 'users.edit'],
            ]);

            expect($template->getAllDefaultPermissions())->toBe(['users.view', 'users.edit']);
        });

        it('returns empty array when no permissions', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Empty Template',
                'slug' => 'empty-template',
                'guard_name' => 'web',
            ]);

            expect($template->getAllDefaultPermissions())->toBe([]);
        });

        it('inherits permissions from parent', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
                'default_permissions' => ['users.view'],
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
                'default_permissions' => ['posts.view'],
            ]);

            $permissions = $child->getAllDefaultPermissions();

            expect($permissions)->toContain('users.view')
                ->and($permissions)->toContain('posts.view');
        });

        it('merges permissions uniquely from parent', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
                'default_permissions' => ['users.view', 'common.permission'],
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
                'default_permissions' => ['posts.view', 'common.permission'],
            ]);

            $permissions = $child->getAllDefaultPermissions();

            // Should be unique
            $unique = array_unique($permissions);
            expect(count($permissions))->toBe(count($unique));
        });
    });

    describe('getAncestors', function (): void {
        it('returns empty collection when no parent', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Root',
                'slug' => 'root',
                'guard_name' => 'web',
            ]);

            expect($template->getAncestors())->toBeEmpty();
        });

        it('returns all ancestors in order', function (): void {
            $grandparent = RoleTemplate::create([
                'name' => 'Grandparent',
                'slug' => 'grandparent',
                'guard_name' => 'web',
            ]);

            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
                'parent_id' => $grandparent->id,
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            $ancestors = $child->getAncestors();

            expect($ancestors)->toHaveCount(2)
                ->and($ancestors->first()->id)->toBe($parent->id)
                ->and($ancestors->last()->id)->toBe($grandparent->id);
        });
    });

    describe('getDescendants', function (): void {
        it('returns empty collection when no children', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Leaf',
                'slug' => 'leaf',
                'guard_name' => 'web',
            ]);

            expect($template->getDescendants())->toBeEmpty();
        });

        it('returns all descendants recursively', function (): void {
            $root = RoleTemplate::create([
                'name' => 'Root',
                'slug' => 'root',
                'guard_name' => 'web',
            ]);

            $child1 = RoleTemplate::create([
                'name' => 'Child 1',
                'slug' => 'child-1',
                'guard_name' => 'web',
                'parent_id' => $root->id,
            ]);

            $grandchild = RoleTemplate::create([
                'name' => 'Grandchild',
                'slug' => 'grandchild',
                'guard_name' => 'web',
                'parent_id' => $child1->id,
            ]);

            $descendants = $root->getDescendants();

            expect($descendants)->toHaveCount(2)
                ->and($descendants->pluck('id')->toArray())->toContain($child1->id)
                ->and($descendants->pluck('id')->toArray())->toContain($grandchild->id);
        });
    });

    describe('isAncestorOf', function (): void {
        it('returns true when template is ancestor', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            expect($parent->isAncestorOf($child))->toBeTrue();
        });

        it('returns false when template is not ancestor', function (): void {
            $template1 = RoleTemplate::create([
                'name' => 'Template 1',
                'slug' => 'template-1',
                'guard_name' => 'web',
            ]);

            $template2 = RoleTemplate::create([
                'name' => 'Template 2',
                'slug' => 'template-2',
                'guard_name' => 'web',
            ]);

            expect($template1->isAncestorOf($template2))->toBeFalse();
        });
    });

    describe('isDescendantOf', function (): void {
        it('returns true when template is descendant', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            expect($child->isDescendantOf($parent))->toBeTrue();
        });

        it('returns false when template is not descendant', function (): void {
            $template1 = RoleTemplate::create([
                'name' => 'Template 1',
                'slug' => 'template-1',
                'guard_name' => 'web',
            ]);

            $template2 = RoleTemplate::create([
                'name' => 'Template 2',
                'slug' => 'template-2',
                'guard_name' => 'web',
            ]);

            expect($template1->isDescendantOf($template2))->toBeFalse();
        });
    });

    describe('getDepth', function (): void {
        it('returns 0 for root template', function (): void {
            $root = RoleTemplate::create([
                'name' => 'Root',
                'slug' => 'root',
                'guard_name' => 'web',
            ]);

            expect($root->getDepth())->toBe(0);
        });

        it('returns correct depth for nested template', function (): void {
            $root = RoleTemplate::create([
                'name' => 'Root',
                'slug' => 'root',
                'guard_name' => 'web',
            ]);

            $level1 = RoleTemplate::create([
                'name' => 'Level 1',
                'slug' => 'level-1',
                'guard_name' => 'web',
                'parent_id' => $root->id,
            ]);

            $level2 = RoleTemplate::create([
                'name' => 'Level 2',
                'slug' => 'level-2',
                'guard_name' => 'web',
                'parent_id' => $level1->id,
            ]);

            expect($level1->getDepth())->toBe(1)
                ->and($level2->getDepth())->toBe(2);
        });
    });

    describe('isRoot', function (): void {
        it('returns true for root template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Root',
                'slug' => 'root',
                'guard_name' => 'web',
            ]);

            expect($template->isRoot())->toBeTrue();
        });

        it('returns false for non-root template', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            expect($child->isRoot())->toBeFalse();
        });
    });

    describe('scopeForOwner', function (): void {
        it('returns all when owner feature is disabled', function (): void {
            config(['filament-authz.owner.enabled' => false]);

            RoleTemplate::create([
                'name' => 'Template 1',
                'slug' => 'template-1',
                'guard_name' => 'web',
            ]);

            RoleTemplate::create([
                'name' => 'Template 2',
                'slug' => 'template-2',
                'guard_name' => 'web',
            ]);

            $results = RoleTemplate::forOwner(null)->get();

            expect($results)->toHaveCount(2);
        });

        it('filters by owner when enabled', function (): void {
            config(['filament-authz.owner.enabled' => true]);

            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            RoleTemplate::create([
                'name' => 'Owned Template',
                'slug' => 'owned-template',
                'guard_name' => 'web',
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->id,
            ]);

            RoleTemplate::create([
                'name' => 'Global Template',
                'slug' => 'global-template',
                'guard_name' => 'web',
            ]);

            $results = RoleTemplate::forOwner($user, includeGlobal: false)->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->name)->toBe('Owned Template');
        });

        it('includes global templates when includeGlobal is true', function (): void {
            config(['filament-authz.owner.enabled' => true]);

            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            RoleTemplate::create([
                'name' => 'Owned Template',
                'slug' => 'owned-template',
                'guard_name' => 'web',
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->id,
            ]);

            RoleTemplate::create([
                'name' => 'Global Template',
                'slug' => 'global-template',
                'guard_name' => 'web',
                'owner_type' => null,
                'owner_id' => null,
            ]);

            $results = RoleTemplate::forOwner($user, includeGlobal: true)->get();

            expect($results)->toHaveCount(2);
        });
    });

    describe('cascade delete behavior', function (): void {
        it('reassigns children to deleted template parent', function (): void {
            $grandparent = RoleTemplate::create([
                'name' => 'Grandparent',
                'slug' => 'grandparent',
                'guard_name' => 'web',
            ]);

            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
                'parent_id' => $grandparent->id,
            ]);

            $child = RoleTemplate::create([
                'name' => 'Child',
                'slug' => 'child',
                'guard_name' => 'web',
                'parent_id' => $parent->id,
            ]);

            $parent->delete();

            $child->refresh();

            expect($child->parent_id)->toBe($grandparent->id);
        });
    });

    describe('casts', function (): void {
        it('casts default_permissions to array', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Template',
                'slug' => 'template',
                'guard_name' => 'web',
                'default_permissions' => ['perm1', 'perm2'],
            ]);

            $template->refresh();

            expect($template->default_permissions)->toBeArray();
        });

        it('casts metadata to array', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Template',
                'slug' => 'template',
                'guard_name' => 'web',
                'metadata' => ['key' => 'value'],
            ]);

            $template->refresh();

            expect($template->metadata)->toBeArray();
        });

        it('casts is_system to boolean', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Template',
                'slug' => 'template',
                'guard_name' => 'web',
                'is_system' => 1,
            ]);

            expect($template->is_system)->toBeBool();
        });

        it('casts is_active to boolean', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Template',
                'slug' => 'template',
                'guard_name' => 'web',
                'is_active' => 0,
            ]);

            expect($template->is_active)->toBeBool();
        });
    });
});
