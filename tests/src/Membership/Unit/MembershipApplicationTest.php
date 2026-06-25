<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->applicant = User::query()->create([
        'name' => 'Applicant',
        'email' => 'applicant@example.com',
        'password' => 'secret',
    ]);
});

it('creates a membership application', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'I am the rightful owner.',
    ]);

    expect($application)
        ->id->toBeUuid()
        ->status->toBe(ApplicationStatus::Pending)
        ->justification->toBe('I am the rightful owner.');
});

it('casts status to enum', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing enum cast.',
    ]);

    expect($application->status)->toBeInstanceOf(ApplicationStatus::class);
});

it('casts meta to array', function (): void {
    $meta = ['evidence' => ['file1.pdf'], 'notes' => 'Additional info'];
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing meta cast.',
        'meta' => $meta,
    ]);

    expect($application->meta)->toBe($meta);
});

it('has polymorphic subject relationship', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing relationship.',
    ]);

    expect($application->subject)->toBeInstanceOf(TestSubject::class);
});

it('has applicant relationship', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing applicant.',
    ]);

    expect($application->applicant)->toBeInstanceOf(User::class);
});

it('can be approved', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing approval.',
    ]);

    $application->update([
        'status' => ApplicationStatus::Approved,
        'granted_role' => 'admin',
        'reviewed_at' => now(),
    ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Approved)
        ->granted_role->toBe('admin');
});

it('can be rejected', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing rejection.',
    ]);

    $application->update([
        'status' => ApplicationStatus::Rejected,
        'reviewer_note' => 'Insufficient evidence.',
        'reviewed_at' => now(),
    ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Rejected)
        ->reviewer_note->toBe('Insufficient evidence.');
});

it('can be cancelled', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Testing cancellation.',
    ]);

    $application->update([
        'status' => ApplicationStatus::Cancelled,
        'cancelled_at' => now(),
    ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Cancelled);
});

it('rejects invalid status transitions', function (): void {
    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $this->applicant->getKey(),
        'status' => ApplicationStatus::Approved,
        'justification' => 'Already approved.',
    ]);

    expect($application->status->isTerminal())->toBeTrue();
});
