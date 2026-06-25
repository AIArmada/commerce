<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\ApplyForMembershipAction;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationSubmitted;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Event;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
    Event::fake([MembershipApplicationSubmitted::class]);

    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->user = User::query()->create([
        'name' => 'User',
        'email' => 'user@example.com',
        'password' => 'secret',
    ]);
});

it('creates a pending application', function (): void {
    $application = ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: 'I want to manage this entity.',
    );

    expect($application)
        ->status->toBe(ApplicationStatus::Pending)
        ->justification->toBe('I want to manage this entity.')
        ->applicant_id->toBe($this->user->getKey());
});

it('rejects applications without justification', function (): void {
    $this->expectException(ValueError::class);

    ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: '',
    );
});

it('dispatches MembershipApplicationSubmitted event', function (): void {
    ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: 'Claiming this entity.',
    );

    Event::assertDispatched(MembershipApplicationSubmitted::class);
});

it('stores meta data with the application', function (): void {
    $meta = ['evidence_url' => 'https://example.com/proof.pdf'];

    $application = ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: 'With evidence.',
        meta: $meta,
    );

    expect($application->meta)->toBe($meta);
});

it('associates application with the correct subject', function (): void {
    $application = ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: 'Claiming this subject.',
    );

    expect($application)
        ->subject_type->toBe($this->subject->getMorphClass())
        ->subject_id->toBe($this->subject->getKey());
});

it('allows multiple applications on the same subject', function (): void {
    $user2 = User::query()->create([
        'name' => 'User 2',
        'email' => 'user2@example.com',
        'password' => 'secret',
    ]);

    $application1 = ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        justification: 'First application.',
    );

    $application2 = ApplyForMembershipAction::make()->handle(
        subject: $this->subject,
        user: $user2,
        justification: 'Second application.',
    );

    expect($application1->id)->not->toBe($application2->id);
    expect(MembershipApplication::query()->where('subject_id', $this->subject->getKey())->count())->toBe(2);
});
