<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages\CreateOrganization;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages\ViewOrganization;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Models\Organization;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

uses(FilamentOrganizationsTestCase::class);

it('validates organization slug uniqueness on edit', function (): void {
    $owner = User::factory()->create();
    $first = CreateOrganizationAction::make()->handle($owner, ['name' => 'First Workspace']);
    $second = CreateOrganizationAction::make()->handle($owner, ['name' => 'Second Workspace']);

    $schema = OrganizationResource::form(Schema::make())->model(Organization::class);

    $slugInput = collect($schema->getComponents())
        ->firstWhere(fn ($component): bool => $component->getName() === 'slug');

    expect($slugInput)->not->toBeNull();

    $uniqueRule = null;

    foreach ($slugInput->getValidationRules() as $rule) {
        $resolved = $rule instanceof Closure ? $rule($slugInput, Organization::class) : $rule;

        if ($resolved instanceof Unique) {
            $uniqueRule = $resolved;

            break;
        }
    }

    expect($uniqueRule)->not->toBeNull();
});

it('throttles repeated organization creation per user', function (): void {
    config()->set('filament-organizations.rate_limits.create_per_hour', 2);

    $user = User::factory()->create();

    $this->actingAs($user);

    $page = new CreateOrganization;
    $method = new ReflectionMethod(CreateOrganization::class, 'handleRecordCreation');

    $first = $method->invoke($page, ['name' => 'Throttle One']);
    $second = $method->invoke($page, ['name' => 'Throttle Two']);

    expect($first->exists)->toBeTrue()
        ->and($second->exists)->toBeTrue();

    expect(fn () => $method->invoke($page, ['name' => 'Throttle Three']))
        ->toThrow(ValidationException::class, 'Too many organizations');

    RateLimiter::clear(CreateOrganization::creationThrottleKey($user));

    $fourth = $method->invoke($page, ['name' => 'Throttle Four']);

    expect($fourth->exists)->toBeTrue();
});

it('searches transfer candidates with a capped member query', function (): void {
    $owner = User::factory()->create();
    $org = CreateOrganizationAction::make()->handle($owner, ['name' => 'Transfer Search Org']);

    $this->actingAs($owner);

    for ($i = 0; $i < 60; $i++) {
        $member = User::factory()->create(['name' => "Transfer Member {$i}"]);
        app(AddMemberAction::class)->handle($org, $member, MemberRole::Viewer);
    }

    $page = new ViewOrganization;
    $page->record = $org;

    $actions = Closure::bind(fn (): array => $this->getHeaderActions(), $page, ViewOrganization::class)();
    $transfer = collect($actions)->firstWhere(fn ($action): bool => $action->getName() === 'transferOwnership');

    expect($transfer)->not->toBeNull();

    $select = collect($transfer->livewire($page)->record($org)->getForm(Schema::make($page))->getComponents())
        ->firstWhere(fn ($component): bool => $component->getName() === 'user_id');

    expect($select)->not->toBeNull()
        ->and($select->isSearchable())->toBeTrue()
        ->and($select->getOptions())->toBe([]);

    $results = $select->getSearchResults('Transfer Member');

    expect($results)->toHaveCount(50)
        ->and(array_keys($results))->not->toContain((string) $owner->getKey());
});

it('matches transfer candidates with literal wildcard characters', function (): void {
    $owner = User::factory()->create();
    $org = CreateOrganizationAction::make()->handle($owner, ['name' => 'Wildcard Search Org']);

    $this->actingAs($owner);

    $percentMember = User::factory()->create(['name' => '100% Member']);
    app(AddMemberAction::class)->handle($org, $percentMember, MemberRole::Viewer);

    $plainMember = User::factory()->create(['name' => '1000 Member']);
    app(AddMemberAction::class)->handle($org, $plainMember, MemberRole::Viewer);

    $page = new ViewOrganization;
    $page->record = $org;

    $actions = Closure::bind(fn (): array => $this->getHeaderActions(), $page, ViewOrganization::class)();
    $transfer = collect($actions)->firstWhere(fn ($action): bool => $action->getName() === 'transferOwnership');

    expect($transfer)->not->toBeNull();

    $select = collect($transfer->livewire($page)->record($org)->getForm(Schema::make($page))->getComponents())
        ->firstWhere(fn ($component): bool => $component->getName() === 'user_id');

    expect($select)->not->toBeNull();

    $results = $select->getSearchResults('100%');

    expect($results)->toHaveKey((string) $percentMember->getKey())
        ->and($results)->not->toHaveKey((string) $plainMember->getKey());
});
