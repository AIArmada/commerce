<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\ActionMacros;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

afterEach(function (): void {
    Mockery::close();
});

beforeEach(function (): void {
    ActionMacros::register();
});

test('requiresPermission denies when unauthenticated', function (): void {
    $action = Action::make('test')->requiresPermission('orders.view');

    expect($action->isAuthorized())->toBeFalse()
        ->and($action->isVisible())->toBeFalse();
});

test('requiresPermission allows when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Macro User',
        'email' => 'macro-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    $this->app->instance(PermissionAggregator::class, $aggregator);

    $action = Action::make('test')->requiresPermission('orders.view');

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresResourcePermission delegates to contextual service when resource provided', function (): void {
    $user = User::create([
        'name' => 'Macro User 2',
        'email' => 'macro-user-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $resource = new class extends Model
    {
        public $timestamps = false;

        protected $guarded = [];
    };

    $contextAuth = Mockery::mock(ContextualAuthorizationService::class);
    $contextAuth->shouldReceive('canForResource')
        ->withArgs(
            fn (object $passedUser, string $permission, Model $passedResource): bool => ($passedUser->getKey() === $user->getKey()) &&
            ($permission === 'orders.view') &&
            ($passedResource === $resource)
        )
        ->andReturn(true);

    $this->app->instance(ContextualAuthorizationService::class, $contextAuth);

    $action = Action::make('test')->requiresResourcePermission('orders.view', $resource);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresOwnership only allows when user owns resource', function (): void {
    $user = User::create([
        'name' => 'Owner User',
        'email' => 'owner-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $resource = new class extends Model
    {
        public $timestamps = false;

        protected $guarded = [];
    };

    $resource->setAttribute('user_id', $user->getKey());

    $action = Action::make('test')->requiresOwnership($resource);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresAnyPermission allows when aggregator grants any permission', function (): void {
    $user = User::create([
        'name' => 'Macro User 3',
        'email' => 'macro-user-3@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $permissions = ['orders.view', 'orders.update'];

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasAnyPermission')
        ->withArgs(fn (object $passedUser, array $passedPermissions): bool => ($passedUser->getKey() === $user->getKey()) && ($passedPermissions === $permissions))
        ->andReturn(true);

    $this->app->instance(PermissionAggregator::class, $aggregator);

    $action = Action::make('test')->requiresAnyPermission($permissions);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresAllPermissions denies when aggregator does not grant all permissions', function (): void {
    $user = User::create([
        'name' => 'Macro User 4',
        'email' => 'macro-user-4@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $permissions = ['orders.view', 'orders.update'];

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasAllPermissions')
        ->withArgs(fn (object $passedUser, array $passedPermissions): bool => ($passedUser->getKey() === $user->getKey()) && ($passedPermissions === $permissions))
        ->andReturn(false);

    $this->app->instance(PermissionAggregator::class, $aggregator);

    $action = Action::make('test')->requiresAllPermissions($permissions);

    expect($action->isAuthorized())->toBeFalse()
        ->and($action->isVisible())->toBeFalse();
});

test('requiresTeamPermission delegates to contextual service', function (): void {
    $user = User::create([
        'name' => 'Macro User 5',
        'email' => 'macro-user-5@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $contextAuth = Mockery::mock(ContextualAuthorizationService::class);
    $contextAuth->shouldReceive('canInTeam')
        ->withArgs(
            fn (object $passedUser, string $permission, string | int $teamId): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view') && ((string) $teamId === '123')
        )
        ->andReturn(true);

    $this->app->instance(ContextualAuthorizationService::class, $contextAuth);

    $action = Action::make('test')->requiresTeamPermission('orders.view', 123);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresResourcePermission falls back to aggregator when resource is null', function (): void {
    $user = User::create([
        'name' => 'Macro User 6',
        'email' => 'macro-user-6@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    $this->app->instance(PermissionAggregator::class, $aggregator);

    $action = Action::make('test')->requiresResourcePermission('orders.view');

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});

test('requiresRole allows when user has any of the roles', function (): void {
    $user = User::create([
        'name' => 'Macro User 7',
        'email' => 'macro-user-7@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    AIArmada\FilamentAuthz\Models\Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $user->assignRole('Admin');

    $action = Action::make('test')->requiresRole(['Admin', 'Manager']);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});
