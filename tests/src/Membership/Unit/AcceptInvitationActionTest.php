<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\AcceptInvitationAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationAccepted;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipInvitationAccepted::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Inviter',
        'email' => 'inviter@app.com',
        'password' => 'secret',
    ]);
    $this->acceptor = User::query()->create([
        'name' => 'Acceptor',
        'email' => 'acceptor@app.com',
        'password' => 'secret',
    ]);
    $this->invitation = $this->withMembershipOwner(fn (): MembershipInvitation => MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'acceptor@app.com',
        'role' => MemberRole::Admin->spatieRoleName(),
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->addDays(7),
    ]));
});

it('creates invitations in the active owner scope', function (): void {
    $owner = OwnerContext::resolve();
    $ownerConfig = MembershipInvitation::ownerScopeConfig();

    expect($owner)->not->toBeNull()
        ->and($ownerConfig->enabled)->toBeTrue()
        ->and($ownerConfig->autoAssignOnCreate)->toBeTrue()
        ->and($this->invitation->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $this->invitation->owner_id)->toBe((string) $owner->getKey())
        ->and(MembershipInvitation::query()->find($this->invitation->id))->not->toBeNull();
});

it('accepts a valid invitation', function (): void {
    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );

    expect($this->invitation->fresh())
        ->accepted_at->not->toBeNull()
        ->accepted_by->toBe($this->acceptor->getKey());
});

it('adds user as member on acceptance', function (): void {
    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(1);
});

it('dispatches MembershipInvitationAccepted event', function (): void {
    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );

    Event::assertDispatched(MembershipInvitationAccepted::class);
});

it('throws on expired invitation', function (): void {
    $this->invitation->update(['expires_at' => now()->subDay()]);

    $this->expectException(RuntimeException::class);

    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );
});

it('throws on revoked invitation', function (): void {
    $this->invitation->update(['revoked_at' => now()]);

    $this->expectException(RuntimeException::class);

    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );
});

it('throws on already accepted invitation', function (): void {
    $this->invitation->update(['accepted_at' => now()]);

    $this->expectException(RuntimeException::class);

    AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $this->acceptor,
    );
});

it('rejects an accepting user with a different email', function (): void {
    $otherUser = User::query()->create([
        'name' => 'Other User',
        'email' => 'other@app.com',
        'password' => 'secret',
    ]);

    expect(fn () => AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $otherUser,
    ))->toThrow(RuntimeException::class, 'Invitation email does not match');

    expect($this->invitation->fresh()->accepted_at)->toBeNull();
});

it('rolls back acceptance when role assignment fails', function (): void {
    $userWithoutRoles = new MembershipUserWithoutRoles;
    $userWithoutRoles->setAttribute('id', $this->acceptor->getKey());
    $userWithoutRoles->setAttribute('email', $this->acceptor->email);
    $userWithoutRoles->exists = true;

    expect(fn () => AcceptInvitationAction::make()->handle(
        invitation: $this->invitation,
        user: $userWithoutRoles,
    ))->toThrow(BadMethodCallException::class);

    expect($this->invitation->fresh()->accepted_at)->toBeNull()
        ->and($this->subject->members()->count())->toBe(0);
});

class MembershipUserWithoutRoles extends Model
{
    protected $table = 'users';
}
