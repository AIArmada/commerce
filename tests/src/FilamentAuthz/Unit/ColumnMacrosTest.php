<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\ColumnMacros;
use Filament\Tables\Columns\TextColumn;

afterEach(function (): void {
    Mockery::close();
});

beforeEach(function (): void {
    ColumnMacros::register();
});

test('visibleForPermission hides column when unauthenticated', function (): void {
    $column = TextColumn::make('permission')->visibleForPermission('orders.view');

    expect($column->isVisible())->toBeFalse();
});

test('visibleForPermission shows column when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Column User',
        'email' => 'column-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $column = TextColumn::make('permission')->visibleForPermission('orders.view');

    expect($column->isVisible())->toBeTrue();
});

test('visibleForAnyPermission shows column when aggregator grants any permission', function (): void {
    $user = User::create([
        'name' => 'Column User 2',
        'email' => 'column-user-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $permissions = ['orders.view', 'orders.update'];

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasAnyPermission')
        ->withArgs(fn (object $passedUser, array $passedPermissions): bool => ($passedUser->getKey() === $user->getKey()) && ($passedPermissions === $permissions))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $column = TextColumn::make('permission')->visibleForAnyPermission($permissions);

    expect($column->isVisible())->toBeTrue();
});

test('formatPermission assigns color based on state keywords', function (): void {
    $column = TextColumn::make('permission')->formatPermission();

    expect($column->getColor('orders.delete'))->toBe('danger')
        ->and($column->getColor('orders.create'))->toBe('success')
        ->and($column->getColor('orders.update'))->toBe('warning')
        ->and($column->getColor('orders.view'))->toBe('info')
        ->and($column->getColor('orders.unknown'))->toBe('gray');
});

test('formatRole assigns primary color', function (): void {
    $column = TextColumn::make('role')->formatRole();

    expect($column->getColor('Admin'))->toBe('primary');
});
