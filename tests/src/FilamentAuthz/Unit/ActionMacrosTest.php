<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\ActionMacros;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

afterEach(function (): void {
    \Mockery::close();
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

    $aggregator = \Mockery::mock(PermissionAggregator::class);
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

    $resource = new class extends Model {
        public $timestamps = false;

        protected $guarded = [];
    };

    $contextAuth = \Mockery::mock(ContextualAuthorizationService::class);
    $contextAuth->shouldReceive('canForResource')
        ->withArgs(fn (object $passedUser, string $permission, Model $passedResource): bool =>
            ($passedUser->getKey() === $user->getKey()) &&
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

    $resource = new class extends Model {
        public $timestamps = false;

        protected $guarded = [];
    };

    $resource->setAttribute('user_id', $user->getKey());

    $action = Action::make('test')->requiresOwnership($resource);

    expect($action->isAuthorized())->toBeTrue()
        ->and($action->isVisible())->toBeTrue();
});
