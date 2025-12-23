<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\FilterMacros;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

afterEach(function (): void {
    Mockery::close();
});

beforeEach(function (): void {
    FilterMacros::register();
});

test('visibleForPermission hides filter when unauthenticated', function (): void {
    $filter = Filter::make('active')->visibleForPermission('orders.view');

    expect($filter->isVisible())->toBeFalse();
});

test('visibleForPermission shows filter when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Filter User',
        'email' => 'filter-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $filter = Filter::make('active')->visibleForPermission('orders.view');

    expect($filter->isVisible())->toBeTrue();
});

test('roleOptions sets role options from database', function (): void {
    $roleA = Role::create(['name' => 'Role A', 'guard_name' => 'web']);
    $roleB = Role::create(['name' => 'Role B', 'guard_name' => 'web']);

    $filter = SelectFilter::make('role_id')->roleOptions();

    expect($filter->getOptions())
        ->toHaveKeys([(string) $roleA->id, (string) $roleB->id]);
});

test('permissionOptions can filter by prefix', function (): void {
    $p1 = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    $p2 = Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $filter = SelectFilter::make('permission_id')->permissionOptions('orders.');

    expect($filter->getOptions())
        ->toHaveKey((string) $p1->id)
        ->not->toHaveKey((string) $p2->id);
});

test('permissionGroupOptions generates group options from permission names', function (): void {
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $filter = SelectFilter::make('permission_group')->permissionGroupOptions();

    expect($filter->getOptions())
        ->toHaveKeys(['orders', 'products']);
});
