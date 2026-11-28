<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipInvitationSent::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Inviter',
        'email' => 'inviter@app.com',
        'password' => 'secret',
    ]);
});

it('creates an invitation', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'invitee@example.com',
        role: MemberRole::Admin,
        inviter: $this->inviter,
    );

    expect($invitation)
        ->toBeInstanceOf(MembershipInvitation::class)
        ->recipient->toBe('invitee@example.com')
        ->role->toBe(MemberRole::Admin->spatieRoleName())
        ->token->not->toBeNull();
});

it('dispatches MembershipInvitationSent event', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'invitee@example.com',
        role: MemberRole::Editor,
        inviter: $this->inviter,
    );

    Event::assertDispatched(
        MembershipInvitationSent::class,
        fn (MembershipInvitationSent $event): bool => $event->delivery->invitationId === (string) $invitation->getKey()
            && $event->delivery->token !== $invitation->token
            && $invitation->matchesToken($event->delivery->token),
    );
});

it('sets expiry date from config by default', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'invitee@example.com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($invitation->expires_at)->not->toBeNull();
});

it('accepts custom expiry date', function (): void {
    $expiresAt = now()->addDays(1);

    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'invitee@example.com',
        role: MemberRole::Admin,
        inviter: $this->inviter,
        expiresAt: $expiresAt,
    );

    expect($invitation->expires_at->toDateString())->toBe($expiresAt->toDateString());
});

it('normalizes the email recipient', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'UPPERCASE@Example.Com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($invitation->recipient)->toBe('uppercase@example.com');
});

it('reuses a pending invitation for the same subject, recipient, and role', function (): void {
    $first = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'pending@example.com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );
    $second = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: ' PENDING@example.com ',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($second->is($first))->toBeTrue()
        ->and(MembershipInvitation::query()
            ->where('subject_id', $this->subject->getKey())
            ->where('recipient', 'pending@example.com')
            ->where('role', MemberRole::Viewer->spatieRoleName())
            ->count())->toBe(1);

    Event::assertDispatchedTimes(MembershipInvitationSent::class, 1);
});

it('expires stale pending invitations without replacing an existing valid invitation', function (): void {
    $attributes = [
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'recipient' => 'repeat@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
    ];

    $expired = $this->withMembershipOwner(fn () => $this->createInvitation([...$attributes, 'expires_at' => now()->subDay()]));
    $valid = $this->withMembershipOwner(fn () => $this->createInvitation([...$attributes, 'expires_at' => now()->addDay()]));

    $result = InviteMemberAction::make()->handle($this->subject, 'repeat@example.com', MemberRole::Viewer, $this->inviter);

    expect($result->is($valid))->toBeTrue()
        ->and($expired->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($valid->fresh()->status)->toBe(InvitationStatus::Pending);

    Event::assertNotDispatched(MembershipInvitationSent::class);
});

it('rejects a nonpositive default invitation lifetime', function (): void {
    config()->set('membership.invitations.default_expiry_days', 0);

    expect(fn () => InviteMemberAction::make()->handle($this->subject, 'expired@example.com', MemberRole::Viewer, $this->inviter))
        ->toThrow(InvalidArgumentException::class, 'The invitation expiry must be in the future.');

    expect(MembershipInvitation::query()->count())->toBe(0);
    Event::assertNotDispatched(MembershipInvitationSent::class);
});

it('stores a fixed length digest independently of the delivery token length', function (): void {
    config()->set('membership.invitations.token_length', 128);

    $invitation = InviteMemberAction::make()->handle($this->subject, 'long-token@example.com', MemberRole::Viewer, $this->inviter);

    expect(mb_strlen($invitation->token))->toBe(64);

    Event::assertDispatched(MembershipInvitationSent::class, fn (MembershipInvitationSent $event): bool => mb_strlen($event->delivery->token) === 128
        && $invitation->matchesToken($event->delivery->token));
});

it('refuses an invitation attributed to a non-user model', function (): void {
    expect(fn () => InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'invitee@example.com',
        role: MemberRole::Viewer,
        inviter: $this->subject,
    ))->toThrow(InvalidArgumentException::class, 'The actor must be a persisted instance');

    expect($this->subject->invitations()->count())->toBe(0);
});
