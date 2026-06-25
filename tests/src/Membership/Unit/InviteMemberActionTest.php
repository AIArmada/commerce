<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    Event::fake();

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
        email: 'invitee@example.com',
        role: MemberRole::Admin,
        inviter: $this->inviter,
    );

    expect($invitation)
        ->toBeInstanceOf(MembershipInvitation::class)
        ->email->toBe('invitee@example.com')
        ->role->toBe(MemberRole::Admin->spatieRoleName())
        ->token->not->toBeNull();
});

it('dispatches MembershipInvitationSent event', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        email: 'invitee@example.com',
        role: MemberRole::Editor,
        inviter: $this->inviter,
    );

    Event::assertDispatched(
        MembershipInvitationSent::class,
        fn (MembershipInvitationSent $event): bool => $event->invitation->is($invitation)
            && $event->token !== $invitation->token
            && $invitation->matchesToken($event->token),
    );
});

it('sets expiry date from config by default', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        email: 'invitee@example.com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($invitation->expires_at)->not->toBeNull();
});

it('accepts custom expiry date', function (): void {
    $expiresAt = now()->addDays(1);

    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        email: 'invitee@example.com',
        role: MemberRole::Admin,
        inviter: $this->inviter,
        expiresAt: $expiresAt,
    );

    expect($invitation->expires_at->toDateString())->toBe($expiresAt->toDateString());
});

it('lowercases email', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        email: 'UPPERCASE@Example.Com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($invitation->email)->toBe('uppercase@example.com');
});
