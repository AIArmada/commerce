<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\ApproveMembershipApplicationAction;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipApplicationApproved;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipApplicationApproved::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->applicant = User::query()->create([
        'name' => 'Applicant',
        'email' => 'applicant@app.com',
        'password' => 'secret',
    ]);
    $this->reviewer = User::query()->create([
        'name' => 'Reviewer',
        'email' => 'reviewer@app.com',
        'password' => 'secret',
    ]);
    $this->application = $this->withMembershipOwner(fn (): MembershipApplication => MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Claiming this entity.',
    ]));
});

it('approves a pending application', function (): void {
    $role = MemberRole::Admin;

    ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: $role,
    );

    expect($this->application->fresh())
        ->status->toBe(ApplicationStatus::Approved)
        ->granted_role->toBe($role->spatieRoleName())
        ->reviewer_id->toBe($this->reviewer->getKey());

    expect(Role::query()->where('name', $role->spatieRoleName())->exists())->toBeTrue();
});

it('adds applicant as a member on approval', function (): void {
    ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: MemberRole::Admin,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(1);
});

it('dispatches MembershipApplicationApproved event', function (): void {
    ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: MemberRole::Editor,
    );

    Event::assertDispatched(MembershipApplicationApproved::class);
});

it('stores reviewer note on approval', function (): void {
    ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: MemberRole::Viewer,
        note: 'Verified credentials.',
    );

    expect($this->application->fresh()->reviewer_note)->toBe('Verified credentials.');
});

it('sets reviewed_at timestamp', function (): void {
    ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: MemberRole::Admin,
    );

    expect($this->application->fresh()->reviewed_at)->not->toBeNull();
});

it('rejects approving a terminal application', function (): void {
    $this->application->update(['status' => ApplicationStatus::Rejected]);

    expect(fn () => ApproveMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        role: MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Only pending membership applications can be approved.');
});
