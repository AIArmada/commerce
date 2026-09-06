<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\RevokeInvitationAction;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Inviter',
        'email' => 'inviter@app.com',
        'password' => 'secret',
    ]);
    $this->revoker = User::query()->create([
        'name' => 'Revoker',
        'email' => 'revoker@app.com',
        'password' => 'secret',
    ]);
    $this->invitation = $this->withMembershipOwner(fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'invitee@example.com',
        'role' => MemberRole::Admin->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
    ]));
});

it('revokes a pending invitation', function (): void {
    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    expect($this->invitation->fresh())
        ->revoked_at->not->toBeNull()
        ->revoked_by->toBe($this->revoker->getKey());
});

it('can revoke an expired invitation', function (): void {
    $this->invitation->forceFill(['expires_at' => now()->subDay()])->save();

    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    expect($this->invitation->fresh()->revoked_at)->not->toBeNull();
});

it('is idempotent for an already revoked invitation', function (): void {
    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    $revokedAt = $this->invitation->fresh()->revoked_at;

    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    expect($this->invitation->fresh()->revoked_at)->toEqual($revokedAt);
});

it('does not revoke an accepted invitation', function (): void {
    $this->invitation->transitionStatus(InvitationStatus::Accepted, $this->inviter);

    expect(fn () => RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    ))->toThrow(LogicException::class);

    expect($this->invitation->fresh()->revoked_at)->toBeNull();
});

it('does not revoke an invitation whose expiry has been materialized', function (): void {
    $this->invitation->transitionStatus(InvitationStatus::Expired);

    expect(fn () => RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    ))->toThrow(LogicException::class);

    expect($this->invitation->fresh()->revoked_at)->toBeNull();
});
