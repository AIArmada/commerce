<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\ArchiveOrganizationAction;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Actions\MakeOrganizationPrivateAction;
use AIArmada\Organizations\Actions\MakeOrganizationPublicAction;
use AIArmada\Organizations\Actions\RestoreOrganizationAction;
use AIArmada\Organizations\Actions\SuspendOrganizationAction;
use AIArmada\Organizations\Actions\TransferOrganizationOwnershipAction;
use AIArmada\Organizations\Contracts\OrganizationAuthorization;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(OrganizationsTestCase::class);

it('rejects creation input that would violate column limits or types', function (): void {
    $creator = User::factory()->create();

    expect(fn () => CreateOrganizationAction::make()->handle($creator, ['name' => str_repeat('n', 256)]))
        ->toThrow(InvalidArgumentException::class, '255 characters')
        ->and(fn () => CreateOrganizationAction::make()->handle($creator, ['name' => ['not-a-string']]))
        ->toThrow(InvalidArgumentException::class, 'name is required')
        ->and(fn () => CreateOrganizationAction::make()->handle($creator, ['name' => 'Ok', 'slug' => str_repeat('s', 256)]))
        ->toThrow(InvalidArgumentException::class, '255 characters')
        ->and(fn () => CreateOrganizationAction::make()->handle($creator, ['name' => 'Ok', 'description' => ['not-a-string']]))
        ->toThrow(InvalidArgumentException::class, 'description must be a string');
});

it('ignores lifecycle fields passed through mass assignment', function (): void {
    $organization = new Organization([
        'name' => 'Guarded Circle',
        'slug' => 'guarded-circle',
        'status' => OrganizationStatus::Archived->value,
        'visibility' => OrganizationVisibility::Public->value,
        'created_by' => (string) Str::uuid(),
        'published_at' => now(),
    ]);
    $organization->forceFill(['created_by' => (string) Str::uuid()]);
    $organization->save();

    expect($organization->fresh())
        ->status->toBe(OrganizationStatus::Active)
        ->visibility->toBe(OrganizationVisibility::Private)
        ->published_at->toBeNull();
});

it('refuses to publish a suspended or archived organization', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Inactive Circle']);

    SuspendOrganizationAction::make()->handle($organization, $creator);

    expect(fn () => MakeOrganizationPublicAction::make()->handle($organization->fresh(), $creator))
        ->toThrow(LogicException::class, 'Only active organizations can be made public');

    ArchiveOrganizationAction::make()->handle($organization->fresh(), $creator);

    expect(fn () => MakeOrganizationPublicAction::make()->handle($organization->fresh(), $creator))
        ->toThrow(LogicException::class, 'Only active organizations can be made public');
});

it('restores a public organization back to private instead of re-publishing it', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Republished Circle']);

    MakeOrganizationPublicAction::make()->handle($organization, $creator);
    SuspendOrganizationAction::make()->handle($organization->fresh(), $creator);
    RestoreOrganizationAction::make()->handle($organization->fresh(), $creator);

    $restored = $organization->fresh();

    expect($restored)
        ->status->toBe(OrganizationStatus::Active)
        ->visibility->toBe(OrganizationVisibility::Private)
        ->published_at->toBeNull()
        ->privatized_at->not->toBeNull();
});

it('clears the published timestamp when an organization goes private', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Toggle Circle']);

    MakeOrganizationPublicAction::make()->handle($organization, $creator);
    expect($organization->fresh()->published_at)->not->toBeNull();

    MakeOrganizationPrivateAction::make()->handle($organization->fresh(), $creator);

    expect($organization->fresh()->published_at)->toBeNull();
});

it('denies unknown abilities even for owners and admins', function (): void {
    $creator = User::factory()->create();
    $admin = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Strict Circle']);
    AddMemberAction::make()->handle($organization, $admin, MemberRole::Admin);

    $authorization = app(OrganizationAuthorization::class);

    expect(fn () => $authorization->authorize($creator, $organization, 'organization.typo-ability'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $authorization->authorize($admin, $organization, 'organization.typo-ability'))
        ->toThrow(AuthorizationException::class);

    $authorization->authorize($creator, $organization, 'organization.transfer-ownership');
    $authorization->authorize($admin, $organization, 'organization.change-status');

    expect(true)->toBeTrue();
});

it('rejects an ownership transfer target from a different member model', function (): void {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Typed Circle']);
    AddMemberAction::make()->handle($organization, $member, MemberRole::Admin);

    $otherOrg = CreateOrganizationAction::make()->handle($creator, ['name' => 'Unrelated Circle']);
    $otherOrg->id = $member->getKey();
    $otherOrg->save();

    expect(fn () => TransferOrganizationOwnershipAction::make()->handle($organization, $creator, $otherOrg->refresh()))
        ->toThrow(InvalidArgumentException::class, 'member model');
});

it('drops and recreates the organization tables through migration rollback', function (): void {
    $organizationsTable = (string) config('organizations.database.tables.organizations', 'organizations');
    $membersTable = (string) config('organizations.database.tables.members', 'organization_members');
    $base = dirname(__DIR__, 4) . '/packages/organizations/database/migrations/';

    $createOrganizations = require $base . '2000_01_01_000001_create_organizations_table.php';
    $createMembers = require $base . '2000_01_01_000002_create_organization_members_table.php';

    expect(Schema::hasTable($organizationsTable))->toBeTrue()
        ->and(Schema::hasTable($membersTable))->toBeTrue();

    try {
        $createMembers->down();
        $createOrganizations->down();

        expect(Schema::hasTable($organizationsTable))->toBeFalse()
            ->and(Schema::hasTable($membersTable))->toBeFalse();
    } finally {
        $createOrganizations->up();
        $createMembers->up();
    }

    expect(Schema::hasTable($organizationsTable))->toBeTrue()
        ->and(Schema::hasTable($membersTable))->toBeTrue();
});
