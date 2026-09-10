<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->subject2 = TestSubject::query()->create(['name' => 'Test Subject 2']);
    $this->user = User::query()->create([
        'name' => 'User',
        'email' => 'user@app.com',
        'password' => 'secret',
    ]);
});

it('provides members relationship', function (): void {
    expect($this->subject->members())->toBeInstanceOf(BelongsToMany::class);
});

it('provides applications relationship', function (): void {
    expect($this->subject->applications())->toBeInstanceOf(MorphMany::class);
});

it('provides invitations relationship', function (): void {
    expect($this->subject->invitations())->toBeInstanceOf(MorphMany::class);
});

it('member applications are scoped to the subject', function (): void {
    $application1 = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->user->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Claim for subject 1.',
    ]);

    $application2 = MembershipApplication::query()->create([
        'subject_type' => $this->subject2->getMorphClass(),
        'subject_id' => $this->subject2->getKey(),
        'applicant_id' => $this->user->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Claim for subject 2.',
    ]);

    expect($this->subject->applications)->toHaveCount(1);
    expect($this->subject->applications->first()->id)->toBe($application1->id);
});

it('member invitations are scoped to the subject', function (): void {
    $invitation1 = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'user1@app.com',
        'role' => 'admin',
        'invited_by' => $this->user->getKey(),
    ]);

    $invitation2 = $this->createInvitation([
        'subject_type' => $this->subject2->getMorphClass(),
        'subject_id' => $this->subject2->getKey(),
        'email' => 'user2@app.com',
        'role' => 'editor',
        'invited_by' => $this->user->getKey(),
    ]);

    expect($this->subject->invitations)->toHaveCount(1);
    expect($this->subject->invitations->first()->id)->toBe($invitation1->id);
});

it('cancels pending records and preserves terminal history when a subject is deleted', function (): void {
    $pendingApplication = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->user->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Pending application.',
    ]);
    $terminalApplication = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->user->getKey(),
        'status' => ApplicationStatus::Rejected,
        'justification' => 'Rejected application.',
    ]);
    $pendingInvitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'pending@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->user->getKey(),
    ]);
    $terminalInvitation = $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'accepted@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->user->getKey(),
    ]);
    $terminalInvitation->transitionStatus(InvitationStatus::Accepted, $this->user);

    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Viewer,
    );

    $subjectId = $this->subject->getKey();
    $this->subject->delete();

    expect(TestSubject::query()->find($subjectId))->toBeNull()
        ->and($pendingApplication->fresh())
        ->status->toBe(ApplicationStatus::Cancelled)
        ->cancelled_at->not->toBeNull()
        ->and($terminalApplication->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($pendingInvitation->fresh())
        ->status->toBe(InvitationStatus::Revoked)
        ->revoked_at->not->toBeNull()
        ->last_state_change_at->not->toBeNull()
        ->and($terminalInvitation->fresh()->status)->toBe(InvitationStatus::Accepted)
        ->and(DB::table('test_subject_members')
            ->where('test_subject_id', $subjectId)
            ->where('user_id', $this->user->getKey())
            ->exists())->toBeTrue()
        ->and(User::query()->find($this->user->getKey()))->not->toBeNull();

    setPermissionsTeamId($subjectId);
    $this->user->unsetRelation('roles');
    expect($this->user->hasRole(MemberRole::Viewer->spatieRoleName()))->toBeTrue();
    setPermissionsTeamId(null);
});

it('cascades pending lifecycle records across owner scopes', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Cascade Owner A',
        'email' => 'cascade-owner-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Cascade Owner B',
        'email' => 'cascade-owner-b@example.com',
        'password' => 'secret',
    ]);

    $application = OwnerContext::withOwner($ownerA, fn (): MembershipApplication => MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $ownerA->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Cross-owner pending application.',
    ]));
    $invitation = OwnerContext::withOwner($ownerB, fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'cross-owner@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $ownerB->getKey(),
    ]));

    $this->subject->delete();

    $application = MembershipApplication::withoutOwnerScope()->findOrFail($application->getKey());
    $invitation = MembershipInvitation::withoutOwnerScope()->findOrFail($invitation->getKey());

    expect($application->status)->toBe(ApplicationStatus::Cancelled)
        ->and($invitation->status)->toBe(InvitationStatus::Revoked);
});
