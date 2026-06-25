<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\RevokeInvitationAction;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

uses(MembershipTestCase::class);

it('isolates membership lifecycle records by owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Membership Owner A',
        'email' => 'membership-owner-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Membership Owner B',
        'email' => 'membership-owner-b@example.com',
        'password' => 'secret',
    ]);
    $subject = TestSubject::query()->create(['name' => 'Shared Subject']);

    $applicationA = OwnerContext::withOwner($ownerA, fn (): MembershipApplication => MembershipApplication::query()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'applicant_id' => $ownerA->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Owner A application.',
    ]));
    $applicationB = OwnerContext::withOwner($ownerB, fn (): MembershipApplication => MembershipApplication::query()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'applicant_id' => $ownerB->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Owner B application.',
    ]));

    expect($applicationA->owner_id)->toBe($ownerA->getKey())
        ->and($applicationB->owner_id)->toBe($ownerB->getKey());
    expect(OwnerContext::withOwner(
        $ownerA,
        fn (): array => MembershipApplication::query()->pluck('id')->all(),
    ))->toBe([$applicationA->id]);
    expect(OwnerContext::withOwner(
        $ownerB,
        fn (): array => MembershipApplication::query()->pluck('id')->all(),
    ))->toBe([$applicationB->id]);
});

it('rejects cross-owner membership mutations and owner mass assignment', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Membership Writer A',
        'email' => 'membership-writer-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Membership Writer B',
        'email' => 'membership-writer-b@example.com',
        'password' => 'secret',
    ]);
    $subject = TestSubject::query()->create(['name' => 'Mutation Subject']);

    $invitationB = OwnerContext::withOwner($ownerB, fn (): MembershipInvitation => MembershipInvitation::query()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'email' => 'member@example.com',
        'role' => 'viewer',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $ownerB->getKey(),
    ]));

    expect(fn () => OwnerContext::withOwner(
        $ownerA,
        fn () => RevokeInvitationAction::run($invitationB, $ownerA),
    ))->toThrow(ModelNotFoundException::class);

    $invitation = new MembershipInvitation;
    $invitation->fill([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    expect($invitation->owner_type)->toBeNull()
        ->and($invitation->owner_id)->toBeNull();
});
