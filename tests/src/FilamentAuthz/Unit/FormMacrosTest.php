<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\FormMacros;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

function filamentAuthz_makeSchemaLivewire(): LivewireComponent & HasSchemas
{
    return new class extends LivewireComponent implements HasSchemas
    {
        public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
        {
            return null;
        }

        public function getOldSchemaState(string $statePath): mixed
        {
            return null;
        }

        public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Filament\Schemas\Components\Component | Filament\Actions\Action | Filament\Actions\ActionGroup | null
        {
            return null;
        }

        public function getSchema(string $name): ?Schema
        {
            return null;
        }

        public function currentlyValidatingSchema(?Schema $schema): void {}

        public function getDefaultTestingSchemaName(): ?string
        {
            return null;
        }
    };
}

function filamentAuthz_attachComponentToSchema(Filament\Schemas\Components\Component $component): void
{
    $schema = Schema::make(filamentAuthz_makeSchemaLivewire())->components([$component]);

    // Ensure the container is actually set on the component.
    $schema->getComponents(withActions: false, withHidden: true);
}

afterEach(function (): void {
    Mockery::close();
});

beforeEach(function (): void {
    FormMacros::register();
});

test('visibleForPermission hides field when unauthenticated', function (): void {
    $field = TextInput::make('name')->visibleForPermission('orders.view');

    expect($field->isVisible())->toBeFalse();
});

test('visibleForPermission shows field when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Form User',
        'email' => 'form-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $field = TextInput::make('name')->visibleForPermission('orders.view');

    expect($field->isVisible())->toBeTrue();
});

test('visibleForRole shows field when user has any role', function (): void {
    Role::create(['name' => 'Admin', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Form Role User',
        'email' => 'form-role-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $user->assignRole('Admin');

    $field = TextInput::make('name')->visibleForRole(['Admin', 'Manager']);

    expect($field->isVisible())->toBeTrue();
});

test('disabledWithoutPermission disables field when unauthenticated', function (): void {
    $field = TextInput::make('name')->disabledWithoutPermission('orders.view');

    filamentAuthz_attachComponentToSchema($field);

    expect($field->isDisabled())->toBeTrue();
});

test('disabledWithoutPermission enables field when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Form User 2',
        'email' => 'form-user-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $field = TextInput::make('name')->disabledWithoutPermission('orders.view');

    filamentAuthz_attachComponentToSchema($field);

    expect($field->isDisabled())->toBeFalse();
});

test('section visibleForPermission hides section when unauthenticated', function (): void {
    $section = Section::make('Meta')->visibleForPermission('orders.view');

    expect($section->isVisible())->toBeFalse();
});

test('section collapsedWithoutPermission collapses when aggregator denies permission', function (): void {
    $user = User::create([
        'name' => 'Section User',
        'email' => 'section-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(false);

    app()->instance(PermissionAggregator::class, $aggregator);

    $section = Section::make('Meta')->collapsedWithoutPermission('orders.view');

    expect($section->isCollapsed())->toBeTrue();
});

test('section collapsedWithoutPermission is not collapsed when aggregator grants permission', function (): void {
    $user = User::create([
        'name' => 'Section User 2',
        'email' => 'section-user-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $section = Section::make('Meta')->collapsedWithoutPermission('orders.view');

    expect($section->isCollapsed())->toBeFalse();
});
