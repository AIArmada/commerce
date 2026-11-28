<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\RejectMembershipApplicationAction;
use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationRejected;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipApplicationRejected::class]);

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
    $this->application = $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Claiming this entity.',
    ]));
});

it('rejects a pending application', function (): void {
    RejectMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
        note: 'Not eligible.',
    );

    expect($this->application->fresh())
        ->status->toBe(ApplicationStatus::Rejected)
        ->reviewer_note->toBe('Not eligible.');
});

it('dispatches MembershipApplicationRejected event', function (): void {
    RejectMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
    );

    Event::assertDispatched(MembershipApplicationRejected::class);
});

it('sets reviewer on rejection', function (): void {
    RejectMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
    );

    expect($this->application->fresh()->reviewer_id)->toBe($this->reviewer->getKey());
});

it('rejects without note', function (): void {
    RejectMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->reviewer,
    );

    expect($this->application->fresh()->status)->toBe(ApplicationStatus::Rejected);
});

it('does not dispatch lifecycle side effects for a rolled back host transaction', function (): void {
    $notifier = Mockery::mock(MembershipApplicationNotifier::class);
    $notifier->shouldNotReceive('notifyRejected');
    app()->instance(MembershipApplicationNotifier::class, $notifier);

    DB::beginTransaction();

    try {
        RejectMembershipApplicationAction::make()->handle(application: $this->application, reviewer: $this->reviewer);
        Event::assertNotDispatched(MembershipApplicationRejected::class);
    } finally {
        DB::rollBack();
    }

    Event::assertNotDispatched(MembershipApplicationRejected::class);
    expect($this->application->fresh()->status)->toBe(ApplicationStatus::Pending);
});

it('refuses review attribution to a non-user model', function (): void {
    expect(fn () => RejectMembershipApplicationAction::make()->handle(
        application: $this->application,
        reviewer: $this->subject,
    ))->toThrow(InvalidArgumentException::class, 'The actor must be a persisted instance');

    expect($this->application->fresh()->status)->toBe(ApplicationStatus::Pending);
});
