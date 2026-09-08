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
use AIArmada\Organizations\Http\Middleware\CurrentOrganizationMiddleware;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

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
        ->and($organization->created_by)->toBe($creator->getKey())
        ->and($organization->members()->whereKey($creator->getKey())->first()?->pivot?->role)
        ->toBe(MemberRole::Admin->spatieRoleName());

    expect(fn () => RemoveMemberAction::make()->handle($organization, $newOwner))
        ->toThrow(AuthorizationException::class, 'Transfer organization ownership');
});

it('rejects an ownership transfer to a non-member target', function (): void {
    $creator = User::factory()->create();
    $outsider = User::factory()->create();
    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Guarded Circle']);

    expect(fn () => TransferOrganizationOwnershipAction::make()->handle($organization, $creator, $outsider))
        ->toThrow(RuntimeException::class, 'already be an organization member');
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

it('uses the secure config fallback when the route does not override it', function (): void {
    config()->set('organizations.middleware.require_context', true);

    expect(fn () => (new CurrentOrganizationMiddleware)->handle(
        Request::create('/'),
        fn (): never => throw new RuntimeException('The request should not be called.'),
    ))->toThrow(LogicException::class, 'NullCurrentOrganizationResolver');
});

it('allows an explicit global route override', function (): void {
    config()->set('organizations.middleware.require_context', true);

    $result = (new CurrentOrganizationMiddleware)->handle(
        Request::create('/'),
        fn (): string => 'ok',
        'false',
    );

    expect($result)->toBe('ok');
});
