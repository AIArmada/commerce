<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    $invitation1 = MembershipInvitation::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'user1@app.com',
        'role' => 'admin',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->user->getKey(),
    ]);

    $invitation2 = MembershipInvitation::query()->create([
        'subject_type' => $this->subject2->getMorphClass(),
        'subject_id' => $this->subject2->getKey(),
        'email' => 'user2@app.com',
        'role' => 'editor',
        'token' => bin2hex(random_bytes(32)),
        'invited_by' => $this->user->getKey(),
    ]);

    expect($this->subject->invitations)->toHaveCount(1);
    expect($this->subject->invitations->first()->id)->toBe($invitation1->id);
});
