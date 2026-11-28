<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\AddOrganizationMemberAction;
use AIArmada\Organizations\Actions\ArchiveOrganizationAction;
use AIArmada\Organizations\Actions\ChangeOrganizationMemberRoleAction;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Actions\InviteOrganizationMemberAction;
use AIArmada\Organizations\Actions\MakeOrganizationPrivateAction;
use AIArmada\Organizations\Actions\MakeOrganizationPublicAction;
use AIArmada\Organizations\Actions\RemoveOrganizationMemberAction;
use AIArmada\Organizations\Actions\RestoreOrganizationAction;
use AIArmada\Organizations\Actions\SuspendOrganizationAction;
use AIArmada\Organizations\Actions\TransferOrganizationOwnershipAction;
use AIArmada\Organizations\Actions\UpdateOrganizationAction;
use AIArmada\Organizations\Authorization\OrganizationAuthorization;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Extensions\OrganizationLifecycleHook;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    expect(fn () => $authorization->authorize($creator, $organization, 'organization.transfer-ownership'))
        ->not->toThrow(AuthorizationException::class)
        ->and(fn () => $authorization->authorize($admin, $organization, 'organization.change-status'))
        ->not->toThrow(AuthorizationException::class);
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

it('rejects publication using a stale active organization instance', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Stale Circle']);
    $stale = $organization->fresh();

    SuspendOrganizationAction::make()->handle($organization, $creator);

    expect(fn () => MakeOrganizationPublicAction::make()->handle($stale, $creator))
        ->toThrow(LogicException::class, 'Only active organizations can be made public');

    expect($organization->fresh()->isPublic())->toBeFalse();
});

it('denies a former owner attempting to transfer ownership again', function (): void {
    $creator = User::factory()->create();
    $newOwner = User::factory()->create();
    $other = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Single Owner Circle']);
    AddMemberAction::make()->handle($organization, $newOwner, MemberRole::Admin);
    AddMemberAction::make()->handle($organization, $other, MemberRole::Admin);

    TransferOrganizationOwnershipAction::make()->handle($organization, $creator, $newOwner);

    expect(fn () => TransferOrganizationOwnershipAction::make()->handle($organization, $creator, $other))
        ->toThrow(AuthorizationException::class);
    expect($organization->fresh()->ownerMember()->first()->is($newOwner))->toBeTrue();
});

it('authorizes ordinary organization member writes against current membership', function (): void {
    $creator = User::factory()->create();
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Member Authorization']);
    app(AddOrganizationMemberAction::class)->handle($organization, $creator, $admin, MemberRole::Admin);
    app(AddOrganizationMemberAction::class)->handle($organization, $admin, $target, MemberRole::Viewer);
    app(ChangeOrganizationMemberRoleAction::class)->handle($organization, $creator, $admin, MemberRole::Viewer);

    expect(fn () => app(RemoveOrganizationMemberAction::class)->handle($organization, $admin, $target))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(InviteOrganizationMemberAction::class)->handle($organization, $creator, 'owner@example.com', MemberRole::Owner))
        ->toThrow(AuthorizationException::class);

    expect($organization->members()->whereKey($target->getKey())->exists())->toBeTrue();
});

it('authorizes member email lookup before resolving the target', function (): void {
    $creator = User::factory()->create();
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Private Member Lookup']);
    $action = app(AddOrganizationMemberAction::class);
    $action->handle($organization, $creator, $viewer, MemberRole::Viewer);

    expect(fn () => $action->handle($organization, $viewer, $target->email, MemberRole::Viewer))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($organization, $viewer, 'missing@example.test', MemberRole::Viewer))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($organization, $creator, 'missing@example.test', MemberRole::Viewer))
        ->toThrow(ValidationException::class);

    $action->handle($organization, $creator, mb_strtoupper($target->email), MemberRole::Viewer);

    expect($organization->members()->whereKey($target->getKey())->exists())->toBeTrue();
});

it('rejects membership mutations using an unrelated model with the same key', function (): void {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Member Model Guard']);
    app(AddOrganizationMemberAction::class)->handle($organization, $creator, $member, MemberRole::Viewer);
    $unrelated = new Organization;
    $unrelated->setAttribute('id', $member->getKey());

    expect(fn () => app(AddOrganizationMemberAction::class)->handle($organization, $creator, $unrelated, MemberRole::Admin))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ChangeOrganizationMemberRoleAction::class)->handle($organization, $creator, $unrelated, MemberRole::Admin))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(RemoveOrganizationMemberAction::class)->handle($organization, $creator, $unrelated))
        ->toThrow(InvalidArgumentException::class);

    expect($organization->members()->whereKey($member->getKey())->exists())->toBeTrue();
});

it('rejects organization creation by an unrelated model with a matching member key', function (): void {
    $creator = User::factory()->create();
    $unrelated = new Organization;
    $unrelated->setAttribute('id', $creator->getKey());

    expect(fn () => CreateOrganizationAction::make()->handle($unrelated, ['name' => 'Forged Creator']))
        ->toThrow(InvalidArgumentException::class, 'creator must use the organization member model');

    expect(Organization::query()->where('name', 'Forged Creator')->exists())->toBeFalse();
});

it('does not create membership or invoke a lifecycle hook when organization creation is cancelled', function (): void {
    $creator = User::factory()->create();
    $hook = Mockery::mock(OrganizationLifecycleHook::class);
    $hook->shouldNotReceive('created');
    app()->instance(OrganizationLifecycleHook::class, $hook);

    Organization::creating(fn (): bool => false);

    expect(fn () => CreateOrganizationAction::make()->handle($creator, ['name' => 'Cancelled Circle']))
        ->toThrow(RuntimeException::class, 'Organization save was cancelled.');

    expect(Organization::query()->where('name', 'Cancelled Circle')->exists())->toBeFalse();
});

it('does not invoke publication hooks when a visibility transition is cancelled', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Private Circle']);
    $hook = Mockery::mock(OrganizationLifecycleHook::class);
    $hook->shouldNotReceive('visibilityChanged');
    app()->instance(OrganizationLifecycleHook::class, $hook);

    Organization::saving(fn (Organization $record): ?bool => $record->is($organization) ? false : null);

    expect(fn () => MakeOrganizationPublicAction::make()->handle($organization, $creator))
        ->toThrow(RuntimeException::class, 'Organization save was cancelled.');

    expect($organization->fresh()->visibility)->toBe(OrganizationVisibility::Private)
        ->and($organization->fresh()->published_at)->toBeNull();
});

it('rejects a cancelled organization update instead of reporting success', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Original Circle']);

    Organization::saving(fn (Organization $record): ?bool => $record->is($organization) ? false : null);

    expect(fn () => app(UpdateOrganizationAction::class)->handle($organization, $creator, ['name' => 'Changed Circle']))
        ->toThrow(RuntimeException::class, 'Organization save was cancelled.');

    expect($organization->fresh()->name)->toBe('Original Circle');
});
