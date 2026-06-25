<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Inviter',
        'email' => 'inviter@example.com',
        'password' => 'secret',
    ]);
});

it('creates a membership invitation', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'invitee@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation)
        ->id->toBeUuid()
        ->email->toBe('invitee@example.com')
        ->role->toBe('admin');
});

it('lowercases email on create', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'UpperCase@Example.Com',
        'role' => 'editor',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation->email)->toBe('uppercase@example.com');
});

it('generates a unique token', function (): void {
    $invitation1 = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'user1@example.com',
        'role' => 'viewer',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);

    $invitation2 = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'user2@example.com',
        'role' => 'viewer',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation1->token)->not->toBe($invitation2->token);
});

it('matches a plaintext token against hashed storage', function (): void {
    $token = 'plain-text-invitation-token';
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'token@example.com',
        'role' => 'viewer',
        'token' => MembershipInvitation::tokenForStorage($token),
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation->token)->not->toBe($token)
        ->and($invitation->matchesToken($token))->toBeTrue()
        ->and($invitation->matchesToken('wrong-token'))->toBeFalse();
});

it('has polymorphic subject relationship', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'test@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
    ]);

    expect($invitation->subject)->toBeInstanceOf(TestSubject::class);
});

it('is valid when not accepted, revoked, or expired', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'test@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->addDays(7),
    ]);

    expect($invitation->isValid())->toBeTrue();
});

it('is not valid when expired', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'test@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->isValid())->toBeFalse();
});

it('is not valid when accepted', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'test@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
        'accepted_at' => now(),
    ]);

    expect($invitation->isValid())->toBeFalse();
});

it('is not valid when revoked', function (): void {
    $invitation = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'test@example.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->inviter->getKey(),
        'revoked_at' => now(),
    ]);

    expect($invitation->isValid())->toBeFalse();
});
