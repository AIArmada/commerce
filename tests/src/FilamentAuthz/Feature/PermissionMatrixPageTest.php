<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use Filament\Actions\Action;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('permission matrix can select role, toggle, and save permissions', function (): void {
    $user = User::create([
        'name' => 'Matrix Admin',
        'email' => 'matrix-admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $role = Role::create(['name' => 'Manager', 'guard_name' => 'web']);

    $ordersView = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    $ordersUpdate = Permission::create(['name' => 'orders.update', 'guard_name' => 'web']);
    $productsView = Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $role->givePermissionTo($ordersView);

    $page = new PermissionMatrixPage;
    $page->mount();

    $headerActions = (function () use ($page): array {
        $method = new \ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);
        /** @var array<int, Action> $actions */
        $actions = $method->invoke($page);

        return $actions;
    })();

    /** @var Action $selectRoleAction */
    $selectRoleAction = collect($headerActions)
        ->first(fn (Action $action): bool => $action->getName() === 'selectRole');
    $selectRoleAction->livewire($page);
    $selectRoleAction->call(['data' => ['role' => $role->id]]);

    expect($page->selectedRole)->toBe((string) $role->id);

    $matrix = $page->getMatrixData();

    expect($matrix)->toHaveKey('orders')
        ->and($matrix)->toHaveKey('products')
        ->and($matrix['orders'])->toHaveKey('orders.view')
        ->and($matrix['orders']['orders.view']['has'])->toBeTrue();

    $page->togglePermission($ordersUpdate->id);

    /** @var Action $saveChangesAction */
    $saveChangesAction = collect($headerActions)
        ->first(fn (Action $action): bool => $action->getName() === 'saveChanges');
    $saveChangesAction->livewire($page);
    $saveChangesAction->call();

    expect($role->fresh()->hasPermissionTo('orders.update'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('orders.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('products.view'))->toBeFalse();
});


test('permission matrix returns null selected role name when unset', function (): void {
    $user = User::create([
        'name' => 'Matrix Admin 2',
        'email' => 'matrix-admin-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = new PermissionMatrixPage;
    $page->mount();

    expect($page->getSelectedRoleName())->toBeNull();
});
