<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrganizations\FilamentOrganizationsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentOrganizations\Resources\OrganizationResource\RelationManagers\InvitationsRelationManager;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use Filament\Tables\Table;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(FilamentOrganizationsTestCase::class);

function invitationAction(string $name, InvitationsRelationManager $manager): mixed
{
    $table = $manager->table(Table::make($manager));

    $action = collect($table->getHeaderActions())
        ->firstWhere(fn ($candidate): bool => $candidate->getName() === $name)
        ?? collect($table->getActions())
            ->firstWhere(fn ($candidate): bool => $candidate->getName() === $name);

    expect($action)->not->toBeNull();

    return $action->livewire($manager);
}

function invitationOrganization(string $name = 'Invitation Actions Org'): array
{
    $owner = User::factory()->create();
    $org = CreateOrganizationAction::make()->handle($owner, ['name' => $name]);

    test()->actingAs($owner);

    $manager = new InvitationsRelationManager;
    $manager->ownerRecord = $org;

    return [$org, $owner, $manager];
}

it('invites through the relation action', function (): void {
    [$org, $owner, $manager] = invitationOrganization();

    invitationAction('invite', $manager)->call([
        'data' => ['email' => 'invited-member@example.com', 'role' => MemberRole::Viewer->value],
    ]);

    expect($org->invitations()->where('email', 'invited-member@example.com')->exists())->toBeTrue();
});

it('revokes pending invitations through the row action', function (): void {
    [$org, $owner, $manager] = invitationOrganization();

    $invitation = app(InviteMemberAction::class)->handle($org, 'revoke-me@example.com', MemberRole::Viewer, $owner);

    expect($invitation->status)->toBe(InvitationStatus::Pending);

    invitationAction('revoke', $manager)->record($invitation)->call();

    $invitation->refresh();

    expect($invitation->status)->toBe(InvitationStatus::Revoked)
        ->and($invitation->revoked_at)->not->toBeNull();
});

it('hides revoke for settled invitations', function (): void {
    [$org, $owner, $manager] = invitationOrganization();

    $pending = app(InviteMemberAction::class)->handle($org, 'pending-one@example.com', MemberRole::Viewer, $owner);
    $revoked = app(InviteMemberAction::class)->handle($org, 'settled-one@example.com', MemberRole::Viewer, $owner);
    $revoked->transitionStatus(InvitationStatus::Revoked, $owner);
    $revoked->save();

    expect(invitationAction('revoke', $manager)->record($pending)->isVisible())->toBeTrue()
        ->and(invitationAction('revoke', $manager)->record($revoked->fresh())->isVisible())->toBeFalse();
});

it('refuses to revoke invitations from other organizations', function (): void {
    [$org, $owner, $manager] = invitationOrganization('Revoke Home Org');

    $otherOwner = User::factory()->create();
    $otherOrg = CreateOrganizationAction::make()->handle($otherOwner, ['name' => 'Revoke Other Org']);
    $foreign = app(InviteMemberAction::class)->handle($otherOrg, 'foreign-invite@example.com', MemberRole::Viewer, $otherOwner);

    try {
        invitationAction('revoke', $manager)->record($foreign)->call();

        $this->fail('Expected an HttpException for a foreign invitation.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }

    expect($foreign->fresh()->status)->toBe(InvitationStatus::Pending);
});
