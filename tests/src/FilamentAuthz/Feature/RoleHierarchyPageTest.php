<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Filament\Actions\Action;
use Filament\Facades\Filament;

test('role hierarchy actions work end-to-end', function (): void {

    Filament::setCurrentPanel('admin');

    $user = User::create([
        'name' => 'Authz Admin',
        'email' => 'authz-admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $service = app(RoleInheritanceService::class);
    $page = new RoleHierarchyPage;
    $page->mount($service);

    $parent = Role::create(['name' => 'Parent', 'guard_name' => 'web']);

    $headerActions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);
        /** @var array<int, Action> $actions */
        $actions = $method->invoke($page);

        return $actions;
    })();

    /** @var Action $createRoleAction */
    $createRoleAction = collect($headerActions)
        ->first(fn (Action $action): bool => $action->getName() === 'createRole');
    $createRoleAction->livewire($page);
    $createRoleAction->call([
        'data' => [
            'name' => 'Child',
            'guard_name' => 'web',
            'parent_id' => $parent->id,
        ],
    ]);

    $child = Role::where('name', 'Child')->firstOrFail();
    expect($child->parent_role_id)->toEqual($parent->id);

    $page->detachRole((string) $child->id, $service);

    expect(Role::find($child->id)?->parent_role_id)->toBeNull();

    $page->setParent((string) $child->id, (string) $parent->id, $service);

    expect(Role::find($child->id)?->parent_role_id)->toEqual($parent->id);
});

test('role hierarchy prevents circular parent assignment', function (): void {

    Filament::setCurrentPanel('admin');

    $user = User::create([
        'name' => 'Authz Admin 2',
        'email' => 'authz-admin-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $service = app(RoleInheritanceService::class);
    $page = new RoleHierarchyPage;
    $page->mount($service);

    $child = Role::create(['name' => 'Child', 'guard_name' => 'web']);
    $descendant = Role::create(['name' => 'Descendant', 'guard_name' => 'web']);

    // Create initial hierarchy Child -> Descendant.
    Role::whereKey($descendant->id)->update([
        'parent_role_id' => $child->id,
        'level' => 1,
    ]);

    // Try to set Descendant as parent of Child (circular reference).
    $page->setParent((string) $child->id, (string) $descendant->id, $service);

    expect(Role::find($child->id)?->parent_role_id)->toBeNull();
});
