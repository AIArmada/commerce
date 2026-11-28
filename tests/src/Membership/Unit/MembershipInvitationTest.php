<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\ExpireMembershipInvitationAction;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Inviter',
        'email' => 'inviter@example.com',
        'password' => 'secret',
    ]);
});

it('creates a membership invitation', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'invitee@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation)
        ->id->toBeUuid()
        ->recipient->toBe('invitee@example.com')
        ->channel->toBe('email')
        ->role->toBe('admin');
});

it('stores the recipient exactly as provided by the fixture', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'UpperCase@Example.Com',
        'role' => 'editor',
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation->recipient)->toBe('UpperCase@Example.Com')
        ->and($invitation->channel)->toBe('email');
});

it('generates a unique token', function (): void {
    $invitation1 = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'user1@example.com',
        'role' => 'viewer',
        'invited_by' => $this->inviter->getKey(),
    ]);

    $invitation2 = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'user2@example.com',
        'role' => 'viewer',
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation1->token)->not->toBe($invitation2->token);
});

it('matches a plaintext token against hashed storage', function (): void {
    $token = 'plain-text-invitation-token';
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'token@example.com',
        'role' => 'viewer',
        'invited_by' => $this->inviter->getKey(),
    ], $token);

    expect($invitation->token)->not->toBe($token)
        ->and($invitation->matchesToken($token))->toBeTrue()
        ->and($invitation->matchesToken('wrong-token'))->toBeFalse();
});

it('has polymorphic subject relationship', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'test@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation->subject)->toBeInstanceOf(TestSubject::class);
});

it('is valid when not accepted, revoked, or expired', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'test@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->addDays(7),
    ]);

    expect($invitation->isValid())->toBeTrue();
});

it('is not valid when expired', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'test@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->isValid())->toBeFalse();
});

it('does not accept an invitation after its expiry deadline', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'expired@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subDay(),
    ]);

    expect(fn () => $invitation->transitionTo(InvitationStatus::Accepted, $this->inviter))
        ->toThrow(LogicException::class);

    expect($invitation->fresh())
        ->status->toBe(InvitationStatus::Pending)
        ->accepted_at->toBeNull();
});

it('records the actual expiration transition separately from the expiry deadline', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'expired@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->expired_at)->toBeNull()
        ->and(ExpireMembershipInvitationAction::run($invitation))->toBeTrue();

    expect($invitation->fresh())
        ->status->toBe(InvitationStatus::Expired)
        ->expired_at->not->toBeNull()
        ->last_state_change_at->not->toBeNull();
});

it('is not valid when accepted', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'test@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
    ]);
    $invitation->transitionTo(InvitationStatus::Accepted, $this->inviter)->save();

    expect($invitation->isValid())->toBeFalse();
});

it('is not valid when revoked', function (): void {
    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'test@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
    ]);
    $invitation->transitionTo(InvitationStatus::Revoked, $this->inviter)->save();

    expect($invitation->isValid())->toBeFalse();
});

it('is invalid at the exact expiry deadline', function (): void {
    $this->freezeTime();

    $invitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'deadline@example.com',
        'role' => 'admin',
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now(),
    ]);

    expect($invitation->isExpired())->toBeTrue()
        ->and($invitation->isValid())->toBeFalse();
});
