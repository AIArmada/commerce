<?php

declare(strict_types=1);

use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Resources\UserResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Gate;

final class TestUserExtraRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';
}

it('eager-loads roles on UserResource eloquent query', function (): void {
    config()->set('filament-authz.user_resource.model', User::class);

    $user = User::factory()->create();
    $role = Role::findOrCreate('editor', 'web');
    $user->assignRole($role);

    $query = UserResource::getEloquentQuery();

    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('resolves configured relation managers on UserResource', function (): void {
    config()->set('filament-authz.user_resource.relation_managers', [
        TestUserExtraRelationManager::class,
        stdClass::class,
    ]);

    expect(UserResource::getRelations())->toBe([TestUserExtraRelationManager::class]);
});

it('builds timezone and verification datetime fields when configured in user resource fields', function (): void {
    config()->set('filament-authz.user_resource.model', User::class);
    config()->set('filament-authz.user_resource.form.fields', [
        'name',
        'email',
        'timezone',
        'email_verified_at',
        'phone_verified_at',
    ]);

    Gate::before(static fn (): bool => true);

    $method = new ReflectionMethod(UserResource::class, 'getDefaultFormFields');
    /** @var array<int, object> $components */
    $components = $method->invoke(null);

    $byName = collect($components)->keyBy(static fn ($component): string => $component->getName());

    expect($byName->get('timezone'))->toBeInstanceOf(Select::class)
        ->and($byName->get('timezone')->isSearchable())->toBeTrue()
        ->and($byName->get('email_verified_at'))->toBeInstanceOf(DateTimePicker::class)
        ->and($byName->get('phone_verified_at'))->toBeInstanceOf(DateTimePicker::class);
});
