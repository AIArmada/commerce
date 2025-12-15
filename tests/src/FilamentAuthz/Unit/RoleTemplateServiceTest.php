<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new RoleTemplateService;
});

describe('RoleTemplateService', function (): void {
    describe('createTemplate', function (): void {
        it('creates a basic template', function (): void {
            $template = $this->service->createTemplate('Manager');

            expect($template)->toBeInstanceOf(RoleTemplate::class)
                ->and($template->name)->toBe('Manager')
                ->and($template->slug)->toBe('manager')
                ->and($template->guard_name)->toBe('web')
                ->and($template->is_active)->toBeTrue();
        });

        it('creates a template with description', function (): void {
            $template = $this->service->createTemplate('Editor', 'web', 'Can edit content');

            expect($template->description)->toBe('Can edit content');
        });

        it('creates a template with parent', function (): void {
            $parent = RoleTemplate::create([
                'name' => 'Parent',
                'slug' => 'parent',
                'guard_name' => 'web',
            ]);

            $child = $this->service->createTemplate('Child', 'web', null, $parent->id);

            expect($child->parent_id)->toBe($parent->id);
        });

        it('creates a template with default permissions', function (): void {
            $template = $this->service->createTemplate(
                'Editor',
                'web',
                null,
                null,
                ['posts.view', 'posts.edit']
            );

            expect($template->default_permissions)->toBe(['posts.view', 'posts.edit']);
        });

        it('creates a template with metadata', function (): void {
            $template = $this->service->createTemplate(
                'Custom',
                'web',
                null,
                null,
                [],
                ['created_by' => 'admin']
            );

            expect($template->metadata)->toBe(['created_by' => 'admin']);
        });

        it('creates a system template', function (): void {
            $template = $this->service->createTemplate(
                'System Template',
                'web',
                null,
                null,
                [],
                null,
                true
            );

            expect($template->is_system)->toBeTrue();
        });

        it('clears cache after creation', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:templates:hierarchy_tree');

            $this->service->createTemplate('Test');
        });
    });

    describe('updateTemplate', function (): void {
        it('updates template name and generates slug', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Old Name',
                'slug' => 'old-name',
                'guard_name' => 'web',
            ]);

            $updated = $this->service->updateTemplate($template, ['name' => 'New Name']);

            expect($updated->name)->toBe('New Name')
                ->and($updated->slug)->toBe('new-name');
        });

        it('updates template with custom slug', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Test',
                'slug' => 'test',
                'guard_name' => 'web',
            ]);

            $updated = $this->service->updateTemplate($template, [
                'name' => 'New Name',
                'slug' => 'custom-slug',
            ]);

            expect($updated->slug)->toBe('custom-slug');
        });

        it('updates default permissions', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Test',
                'slug' => 'test',
                'guard_name' => 'web',
                'default_permissions' => ['posts.view'],
            ]);

            $updated = $this->service->updateTemplate($template, [
                'default_permissions' => ['posts.view', 'posts.edit'],
            ]);

            expect($updated->default_permissions)->toBe(['posts.view', 'posts.edit']);
        });
    });

    describe('deleteTemplate', function (): void {
        it('deletes a template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Delete Me',
                'slug' => 'delete-me',
                'guard_name' => 'web',
            ]);
            $id = $template->id;

            $result = $this->service->deleteTemplate($template);

            expect($result)->toBeTrue()
                ->and(RoleTemplate::find($id))->toBeNull();
        });
    });

    describe('createRoleFromTemplate', function (): void {
        it('creates a role from template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Editor Template',
                'slug' => 'editor-template',
                'guard_name' => 'web',
                'default_permissions' => [],
            ]);

            $role = $this->service->createRoleFromTemplate($template, 'content-editor');

            expect($role)->toBeInstanceOf(Role::class)
                ->and($role->name)->toBe('content-editor');
        });
    });

    describe('syncRoleWithTemplate', function (): void {
        it('returns null for role without template_id', function (): void {
            $role = Role::create(['name' => 'standalone', 'guard_name' => 'web']);

            $result = $this->service->syncRoleWithTemplate($role);

            expect($result)->toBeNull();
        });

        it('returns null when template not found', function (): void {
            $role = Role::create([
                'name' => 'orphan',
                'guard_name' => 'web',
            ]);
            // Manually set a non-existent template_id
            $role->template_id = 'non-existent-uuid';
            $role->save();

            $result = $this->service->syncRoleWithTemplate($role);

            expect($result)->toBeNull();
        });
    });

    describe('syncAllRolesFromTemplate', function (): void {
        it('syncs all roles from template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Test Template',
                'slug' => 'test-template',
                'guard_name' => 'web',
                'default_permissions' => [],
            ]);

            // Create roles with template_id
            $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
            $role1->template_id = $template->id;
            $role1->save();

            $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
            $role2->template_id = $template->id;
            $role2->save();

            $result = $this->service->syncAllRolesFromTemplate($template);

            expect($result['synced'])->toBe(2)
                ->and($result['failed'])->toBe(0);
        });

        it('reports failed syncs', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Test Template',
                'slug' => 'test-template',
                'guard_name' => 'web',
                'default_permissions' => [],
            ]);

            $result = $this->service->syncAllRolesFromTemplate($template);

            expect($result['synced'])->toBe(0);
        });
    });

    describe('getRootTemplates', function (): void {
        it('returns only root templates', function (): void {
            $root1 = RoleTemplate::create(['name' => 'Root 1', 'slug' => 'root-1', 'guard_name' => 'web', 'is_active' => true]);
            $root2 = RoleTemplate::create(['name' => 'Root 2', 'slug' => 'root-2', 'guard_name' => 'web', 'is_active' => true]);
            RoleTemplate::create(['name' => 'Child', 'slug' => 'child', 'guard_name' => 'web', 'parent_id' => $root1->id, 'is_active' => true]);

            $roots = $this->service->getRootTemplates();

            expect($roots)->toHaveCount(2);
        });

        it('excludes inactive templates', function (): void {
            RoleTemplate::create(['name' => 'Active', 'slug' => 'active', 'guard_name' => 'web', 'is_active' => true]);
            RoleTemplate::create(['name' => 'Inactive', 'slug' => 'inactive', 'guard_name' => 'web', 'is_active' => false]);

            $roots = $this->service->getRootTemplates();

            expect($roots)->toHaveCount(1)
                ->and($roots->first()->name)->toBe('Active');
        });
    });

    describe('getHierarchyTree', function (): void {
        it('returns hierarchy with children', function (): void {
            $root = RoleTemplate::create(['name' => 'Root', 'slug' => 'root', 'guard_name' => 'web', 'is_active' => true]);
            RoleTemplate::create(['name' => 'Child', 'slug' => 'child', 'guard_name' => 'web', 'parent_id' => $root->id, 'is_active' => true]);

            Cache::flush();
            $tree = $this->service->getHierarchyTree();

            expect($tree)->toHaveCount(1)
                ->and($tree->first()->children)->toHaveCount(1);
        });
    });

    describe('findBySlug', function (): void {
        it('finds template by slug', function (): void {
            RoleTemplate::create(['name' => 'Test Template', 'slug' => 'test-template', 'guard_name' => 'web']);

            $found = $this->service->findBySlug('test-template');

            expect($found)->not->toBeNull()
                ->and($found->name)->toBe('Test Template');
        });

        it('returns null for non-existent slug', function (): void {
            $found = $this->service->findBySlug('non-existent');

            expect($found)->toBeNull();
        });
    });

    describe('getActiveTemplates', function (): void {
        it('returns only active templates', function (): void {
            RoleTemplate::create(['name' => 'Active', 'slug' => 'active', 'guard_name' => 'web', 'is_active' => true]);
            RoleTemplate::create(['name' => 'Inactive', 'slug' => 'inactive', 'guard_name' => 'web', 'is_active' => false]);

            $active = $this->service->getActiveTemplates();

            expect($active)->toHaveCount(1);
        });
    });

    describe('getByGuard', function (): void {
        it('returns templates by guard name', function (): void {
            RoleTemplate::create(['name' => 'Web', 'slug' => 'web', 'guard_name' => 'web', 'is_active' => true]);
            RoleTemplate::create(['name' => 'API', 'slug' => 'api', 'guard_name' => 'api', 'is_active' => true]);

            $webTemplates = $this->service->getByGuard('web');

            expect($webTemplates)->toHaveCount(1)
                ->and($webTemplates->first()->name)->toBe('Web');
        });
    });

    describe('getRolesFromTemplate', function (): void {
        it('returns roles created from template', function (): void {
            $template = RoleTemplate::create([
                'name' => 'Test Template',
                'slug' => 'test-template',
                'guard_name' => 'web',
            ]);

            $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
            $role1->template_id = $template->id;
            $role1->save();

            $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
            $role2->template_id = $template->id;
            $role2->save();

            $roles = $this->service->getRolesFromTemplate($template);

            expect($roles)->toHaveCount(2);
        });
    });

    describe('cloneTemplate', function (): void {
        it('clones a template with new name', function (): void {
            $original = RoleTemplate::create([
                'name' => 'Original',
                'slug' => 'original',
                'guard_name' => 'web',
                'description' => 'Original description',
                'default_permissions' => ['posts.view'],
                'is_system' => true,
            ]);

            $clone = $this->service->cloneTemplate($original, 'Clone');

            expect($clone->name)->toBe('Clone')
                ->and($clone->slug)->toBe('clone')
                ->and($clone->description)->toBe('Original description')
                ->and($clone->default_permissions)->toBe(['posts.view'])
                ->and($clone->is_system)->toBeFalse(); // Clones are not system templates
        });
    });

    describe('clearCache', function (): void {
        it('clears hierarchy tree cache', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:templates:hierarchy_tree');

            $this->service->clearCache();
        });
    });
});
