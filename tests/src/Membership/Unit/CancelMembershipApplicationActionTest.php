<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\CancelMembershipApplicationAction;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationCancelled;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipApplicationCancelled::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->applicant = User::query()->create([
        'name' => 'Applicant',
        'email' => 'applicant@app.com',
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

it('cancels a pending application', function (): void {
    CancelMembershipApplicationAction::make()->handle(application: $this->application);

    expect($this->application->fresh())
        ->status->toBe(ApplicationStatus::Cancelled)
        ->cancelled_at->not->toBeNull();
});

it('dispatches MembershipApplicationCancelled event', function (): void {
    CancelMembershipApplicationAction::make()->handle(application: $this->application);

    Event::assertDispatched(MembershipApplicationCancelled::class);
});

it('rejects cancelling an approved application', function (): void {
    $this->application->update(['status' => ApplicationStatus::Approved]);

    expect(fn () => CancelMembershipApplicationAction::make()->handle(application: $this->application))
        ->toThrow(RuntimeException::class, 'Only pending membership applications can be cancelled.');
});
