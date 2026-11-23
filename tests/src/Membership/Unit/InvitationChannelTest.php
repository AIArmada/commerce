<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\AcceptInvitationAction;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Channels\InvitationChannelManager;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\FakeInvitationChannel;
use AIArmada\Membership\Tests\Fixtures\FakeInvitationRecipientVerifier;
use AIArmada\Membership\Tests\Fixtures\RegistersFakeInvitationChannel;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class, RegistersFakeInvitationChannel::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    $this->registerFakeInvitationChannel();
    Event::fake([MembershipInvitationSent::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Channel Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Channel Inviter',
        'email' => 'channel-inviter@app.com',
        'password' => 'secret',
    ]);
});

it('invites over a custom channel and hands the delivery to the integration', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );

    expect($invitation)
        ->channel->toBe(FakeInvitationChannel::CHANNEL)
        ->recipient->toBe('+60123456789')
        ->and($this->fakeChannel->deliveries)->toHaveCount(1)
        ->and($this->fakeChannel->deliveries[0]->channel)->toBe(FakeInvitationChannel::CHANNEL)
        ->and($this->fakeChannel->deliveries[0]->invitationId)->toBe((string) $invitation->getKey())
        ->and($invitation->matchesToken($this->fakeChannel->deliveries[0]->token))->toBeTrue();

    Event::assertDispatched(MembershipInvitationSent::class, function (MembershipInvitationSent $event) use ($invitation): bool {
        return $event->delivery->invitationId === (string) $invitation->getKey();
    });
});

it('defaults to the email channel and preserves email delivery', function (): void {
    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'Email-Invitee@Example.COM',
        role: MemberRole::Editor,
        inviter: $this->inviter,
    );

    expect($invitation)
        ->channel->toBe('email')
        ->recipient->toBe('email-invitee@example.com');

    Event::assertDispatched(MembershipInvitationSent::class, function (MembershipInvitationSent $event): bool {
        return $event->delivery->channel === 'email'
            && $event->delivery->recipient === 'email-invitee@example.com';
    });
});

it('rejects an invalid recipient for the selected channel', function (): void {
    expect(fn () => InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'not-a-phone-number',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    ))->toThrow(InvalidArgumentException::class, 'A valid E.164 phone number is required.');

    expect(MembershipInvitation::query()->count())->toBe(0)
        ->and($this->fakeChannel->deliveries)->toHaveCount(0);
});

it('rejects an invalid email recipient through the default channel', function (): void {
    expect(fn () => InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: 'not-an-email',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    ))->toThrow(InvalidArgumentException::class, 'A valid invitation email address is required.');
});

it('rejects an unregistered channel instead of silently falling back', function (): void {
    expect(fn () => InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
        channel: 'telegram',
    ))->toThrow(InvalidArgumentException::class, 'The invitation channel [telegram] is not registered.');
});

it('keeps pending invitations unique per channel and recipient', function (): void {
    $first = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );
    $second = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );

    expect($second->is($first))->toBeTrue()
        ->and(MembershipInvitation::query()
            ->where('channel', FakeInvitationChannel::CHANNEL)
            ->where('recipient', '+60123456789')
            ->count())->toBe(1);

    Event::assertDispatchedTimes(MembershipInvitationSent::class, 1);
});

it('accepts a custom channel invitation when the integration verifies the identity', function (): void {
    $acceptor = User::query()->create([
        'name' => 'Sms Acceptor',
        'email' => 'sms-acceptor@app.com',
        'password' => 'secret',
    ]);
    $this->fakeVerifier->identities[(string) $acceptor->getKey()] = '+60123456789';

    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Admin,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );

    AcceptInvitationAction::make()->handle(
        invitation: $invitation,
        user: $acceptor,
        token: $this->fakeChannel->deliveries[0]->token,
    );

    expect($invitation->fresh())
        ->status->toBe(InvitationStatus::Accepted)
        ->accepted_by->toBe($acceptor->getKey())
        ->and($this->subject->members()->count())->toBe(1);
});

it('refuses a custom channel acceptance without a trusted verifier match', function (): void {
    $acceptor = User::query()->create([
        'name' => 'Unverified Acceptor',
        'email' => 'unverified-acceptor@app.com',
        'password' => 'secret',
    ]);

    $invitation = InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60123456789',
        role: MemberRole::Admin,
        inviter: $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );

    expect(fn () => AcceptInvitationAction::make()->handle(
        invitation: $invitation,
        user: $acceptor,
    ))->toThrow(RuntimeException::class, 'Invitation recipient identity could not be verified.');

    expect($invitation->fresh())
        ->status->toBe(InvitationStatus::Pending)
        ->accepted_at->toBeNull()
        ->and($this->subject->members()->count())->toBe(0);
});

it('refuses acceptance when a channel has no registered verifier', function (): void {
    $acceptor = User::query()->create([
        'name' => 'No Verifier Acceptor',
        'email' => 'no-verifier@app.com',
        'password' => 'secret',
    ]);
    $invitation = $this->withMembershipOwner(fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'channel' => 'carrier-pigeon',
        'recipient' => '12 Aviary Lane',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->addDay(),
    ]));

    expect(fn () => AcceptInvitationAction::make()->handle(
        invitation: $invitation,
        user: $acceptor,
    ))->toThrow(RuntimeException::class, 'Invitation recipient identity could not be verified.');
});

it('isolates custom channel invitations by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Channel Owner A',
        'email' => 'channel-owner-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Channel Owner B',
        'email' => 'channel-owner-b@example.com',
        'password' => 'secret',
    ]);
    $acceptor = User::query()->create([
        'name' => 'Isolated Acceptor',
        'email' => 'isolated-acceptor@example.com',
        'password' => 'secret',
    ]);

    $invitationB = OwnerContext::withOwner($ownerB, fn (): MembershipInvitation => InviteMemberAction::make()->handle(
        subject: $this->subject,
        recipient: '+60111222333',
        role: MemberRole::Viewer,
        inviter: $ownerB,
        channel: FakeInvitationChannel::CHANNEL,
    ));

    $this->fakeVerifier->identities[(string) $acceptor->getKey()] = '+60111222333';

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => AcceptInvitationAction::make()->handle(
        invitation: $invitationB,
        user: $acceptor,
    )))->toThrow(AuthorizationException::class);

    expect($invitationB->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and(OwnerContext::withOwner(
            $ownerA,
            fn (): int => MembershipInvitation::query()->count(),
        ))->toBe(0)
        ->and(OwnerContext::withOwner(
            $ownerB,
            fn (): int => MembershipInvitation::query()->count(),
        ))->toBe(1);
});

it('exposes registered channels for host integration UIs', function (): void {
    $manager = app(InvitationChannelManager::class);

    expect($manager->hasChannel('email'))->toBeTrue()
        ->and($manager->hasChannel(FakeInvitationChannel::CHANNEL))->toBeTrue()
        ->and($manager->options())->toMatchArray([
            'email' => 'Email',
            FakeInvitationChannel::CHANNEL => 'SMS',
        ])
        ->and($manager->defaultChannelName())->toBe('email');
});

it('resolves a channel and verifier registered through configuration', function (): void {
    $manager = new InvitationChannelManager;
    config()->set('membership.invitations.channels.sms', FakeInvitationChannel::class);
    config()->set('membership.invitations.verifiers.sms', FakeInvitationRecipientVerifier::class);

    expect($manager->hasChannel(FakeInvitationChannel::CHANNEL))->toBeTrue()
        ->and($manager->channel(FakeInvitationChannel::CHANNEL))->toBeInstanceOf(FakeInvitationChannel::class)
        ->and($manager->verifier(FakeInvitationChannel::CHANNEL))->toBeInstanceOf(FakeInvitationRecipientVerifier::class)
        ->and($manager->options())->toHaveKey(FakeInvitationChannel::CHANNEL);
});

it('does not deliver an invitation rolled back by the host', function (): void {
    DB::beginTransaction();

    try {
        InviteMemberAction::run($this->subject, '+60123456789', MemberRole::Viewer, $this->inviter, channel: FakeInvitationChannel::CHANNEL);
        expect($this->fakeChannel->deliveries)->toBeEmpty();
    } finally {
        DB::rollBack();
    }

    expect(MembershipInvitation::query()->count())->toBe(0)
        ->and($this->fakeChannel->deliveries)->toBeEmpty();

    Event::assertNotDispatched(MembershipInvitationSent::class);
});

it('can reissue and expire invitations for the same recipient repeatedly', function (): void {
    config()->set('membership.invitations.default_expiry_days', 1);

    $issue = fn (): MembershipInvitation => InviteMemberAction::run(
        $this->subject,
        '+60123456789',
        MemberRole::Viewer,
        $this->inviter,
        channel: FakeInvitationChannel::CHANNEL,
    );

    $first = $issue();
    $this->travel(2)->days();
    $second = $issue();
    $this->travel(2)->days();
    $third = $issue();

    expect($first->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($second->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($third->fresh()->status)->toBe(InvitationStatus::Pending);
});
