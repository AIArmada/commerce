<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Actions\RemoveMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\ArchiveOrganizationAction;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Actions\MakeOrganizationPrivateAction;
use AIArmada\Organizations\Actions\MakeOrganizationPublicAction;
use AIArmada\Organizations\Actions\RestoreOrganizationAction;
use AIArmada\Organizations\Actions\TransferOrganizationOwnershipAction;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;

uses(OrganizationsTestCase::class);

it('hydrates active private defaults before persistence', function (): void {
    $organization = new Organization;

    expect($organization->status)->toBe(OrganizationStatus::Active)
        ->and($organization->visibility)->toBe(OrganizationVisibility::Private);
});

it('creates a private organization with exactly one owner', function (): void {
    $creator = User::factory()->create();

    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Knowledge Circle']);

    expect($organization)
        ->toBeInstanceOf(Organization::class)
        ->status->toBe(OrganizationStatus::Active)
        ->visibility->toBe(OrganizationVisibility::Private)
        ->created_by->toBe($creator->getKey());

    expect($organization->ownerMember()->count())->toBe(1)
        ->and($organization->ownerMember()->first()?->is($creator))->toBeTrue();
});

it('transfers ownership transactionally and protects the final owner', function (): void {
    $creator = User::factory()->create();
    $newOwner = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Transferable Circle']);

    AddMemberAction::make()->handle($organization, $newOwner, MemberRole::Admin);
    TransferOrganizationOwnershipAction::make()->handle($organization, $creator, $newOwner);

    $organization->refresh();

    expect($organization->ownerMember()->first()?->is($newOwner))->toBeTrue()
        ->and($organization->members()->whereKey($creator->getKey())->first()?->pivot?->role)
        ->toBe(MemberRole::Admin->spatieRoleName());

    expect(fn () => RemoveMemberAction::make()->handle($organization, $newOwner))
        ->toThrow(AuthorizationException::class, 'Transfer organization ownership');
});

it('does not allow a second owner through generic membership actions', function (): void {
    $creator = User::factory()->create();
    $secondUser = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Safe Circle']);

    expect(fn () => AddMemberAction::make()->handle($organization, $secondUser, MemberRole::Owner))
        ->toThrow(AuthorizationException::class, 'second owner');
});

it('transitions visibility without changing lifecycle status', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Visible Circle']);

    MakeOrganizationPublicAction::make()->handle($organization, $creator);
    $organization->refresh();

    expect($organization->visibility)->toBe(OrganizationVisibility::Public)
        ->and($organization->status)->toBe(OrganizationStatus::Active)
        ->and($organization->published_at)->not->toBeNull();

    MakeOrganizationPrivateAction::make()->handle($organization, $creator);
    expect($organization->fresh()->visibility)->toBe(OrganizationVisibility::Private);
});

it('retains terminal lifecycle timestamps after restoration', function (): void {
    $creator = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Lifecycle Circle']);

    ArchiveOrganizationAction::make()->handle($organization, $creator);
    $archivedAt = $organization->fresh()->archived_at;

    RestoreOrganizationAction::make()->handle($organization, $creator);

    expect($organization->fresh())
        ->status->toBe(OrganizationStatus::Active)
        ->archived_at->toEqual($archivedAt);
});
