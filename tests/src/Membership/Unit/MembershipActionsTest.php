<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Actions\ChangeMemberRoleAction;
use AIArmada\Membership\Actions\RemoveMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->user = User::query()->create([
        'name' => 'User',
        'email' => 'user@app.com',
        'password' => 'secret',
    ]);
});

it('adds a member to the subject', function (): void {
    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    expect(Role::where('name', MemberRole::Admin->spatieRoleName())->exists())->toBeTrue();

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(1);
});

it('does not duplicate members', function (): void {
    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(1);
});

it('sets the role on the pivot', function (): void {
    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Editor,
    );

    $pivot = $this->subject->members()->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->role->toBe(MemberRole::Editor->spatieRoleName());
});

it('removes a member from the subject', function (): void {
    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    RemoveMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(0);

    setPermissionsTeamId($this->subject->getKey());
    $this->user->unsetRelation('roles');
    expect($this->user->hasRole(MemberRole::Admin->spatieRoleName()))->toBeFalse();
    setPermissionsTeamId(null);
});

it('is idempotent when removing non-member', function (): void {
    RemoveMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(0);
});

it('changes the role of a member', function (): void {
    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Viewer,
    );

    ChangeMemberRoleAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    $pivot = $this->subject->members()->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->role->toBe(MemberRole::Admin->spatieRoleName());

    setPermissionsTeamId($this->subject->getKey());
    $this->user->unsetRelation('roles');
    expect($this->user->hasRole(MemberRole::Viewer->spatieRoleName()))->toBeFalse()
        ->and($this->user->hasRole(MemberRole::Admin->spatieRoleName()))->toBeTrue();
    setPermissionsTeamId(null);
});

it('rejects changing the role of a non-member', function (): void {
    expect(fn () => ChangeMemberRoleAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Cannot change the role of a non-member.');
});

it('preserves existing members when adding new ones', function (): void {
    $user2 = User::query()->create([
        'name' => 'User 2',
        'email' => 'user2@app.com',
        'password' => 'secret',
    ]);

    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $this->user,
        role: MemberRole::Admin,
    );

    AddMemberAction::make()->handle(
        subject: $this->subject,
        user: $user2,
        role: MemberRole::Viewer,
    );

    $this->subject->load('members');
    expect($this->subject->members)->toHaveCount(2);
});
