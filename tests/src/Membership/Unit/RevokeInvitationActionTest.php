<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Membership\Actions\RevokeInvitationAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
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
    $this->invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'invitee@example.com',
        'role' => MemberRole::Admin->spatieRoleName(),
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);
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
    $this->invitation->update(['expires_at' => now()->subDay()]);

    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    expect($this->invitation->fresh()->revoked_at)->not->toBeNull();
});

it('can revoke regardless of current state', function (): void {
    RevokeInvitationAction::make()->handle(
        invitation: $this->invitation,
        actor: $this->revoker,
    );

    expect($this->invitation->fresh()->revoked_at)->not->toBeNull();
});
