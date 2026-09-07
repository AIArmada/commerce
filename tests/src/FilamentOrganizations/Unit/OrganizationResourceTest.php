<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Actions\CreateOrganizationAction;

uses(FilamentOrganizationsTestCase::class);

it('reads navigation settings from package config', function (): void {
    config()->set('filament-organizations.navigation.group', 'Tenant Workspaces');
    config()->set('filament-organizations.navigation.sort', 42);

    expect(OrganizationResource::getNavigationGroup())->toBe('Tenant Workspaces')
        ->and(OrganizationResource::getNavigationSort())->toBe(42);
});

it('allows authenticated actors to see and create the organization resource', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(OrganizationResource::canViewAny())->toBeTrue()
        ->and(OrganizationResource::canCreate())->toBeTrue();
});

it('pins organization resource access to authenticated actors', function (): void {
    auth()->logout();

    expect(OrganizationResource::canViewAny())->toBeFalse()
        ->and(OrganizationResource::canCreate())->toBeFalse();
});

it('restricts resource queries to organizations the actor belongs to', function (): void {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $visible = CreateOrganizationAction::make()->handle($member, ['name' => 'Visible Workspace']);
    CreateOrganizationAction::make()->handle($outsider, ['name' => 'Hidden Workspace']);

    $this->actingAs($member);

    expect(OrganizationResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$visible->getKey()]);
});
