<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Features\Venues\Actions\ManageVenueSpaceAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

it('validates capacity and actor when saving venue spaces', function (): void {
    $actor = User::factory()->create();
    Gate::define('manageSharedEventCatalog', fn (User $user): bool => $user->is($actor));
    $action = app(ManageVenueSpaceAction::class);
    $data = ['name' => 'Main Hall', 'slug' => 'main-hall', 'capacity' => 80, 'status' => 'active', 'visibility' => 'public'];

    expect(fn () => $action->save(null, $data, null))->toThrow(AuthorizationException::class);
    expect(fn () => $action->save(null, [...$data, 'capacity' => 0], $actor))->toThrow(ValidationException::class);

    $space = $action->save(null, $data, $actor);
    $updated = $action->save($space->id, ['capacity' => 100], $actor);

    expect($updated->capacity)->toBe(100);
    expect($action->delete($space->id, $actor))->toBeTrue();
});
