<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Actions\AcceptInvitationAction;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Actions\ApplyForMembershipAction;
use AIArmada\Membership\Actions\ApproveMembershipApplicationAction;
use AIArmada\Membership\Actions\CancelMembershipApplicationAction;
use AIArmada\Membership\Actions\ChangeMemberRoleAction;
use AIArmada\Membership\Actions\ExpireMembershipInvitationsAction;
use AIArmada\Membership\Actions\InviteMemberAction;
use AIArmada\Membership\Actions\RejectMembershipApplicationAction;
use AIArmada\Membership\Actions\RemoveMemberAction;
use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\MembershipServiceProvider;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);

    $this->subject = TestSubject::query()->create(['name' => 'Regression Subject']);
    $this->inviter = User::query()->create([
        'name' => 'Regression Inviter',
        'email' => 'regression-inviter@app.com',
        'password' => 'secret',
    ]);
});

it('creates a fresh invitation when the previous one expired', function (): void {
    Event::fake([MembershipInvitationSent::class]);

    $stale = $this->withMembershipOwner(fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'returning@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subDay(),
    ]));

    $fresh = InviteMemberAction::make()->handle(
        subject: $this->subject,
        email: 'returning@example.com',
        role: MemberRole::Viewer,
        inviter: $this->inviter,
    );

    expect($fresh->getKey())->not->toBe($stale->getKey())
        ->and($stale->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($fresh->isValid())->toBeTrue();

    Event::assertDispatchedTimes(MembershipInvitationSent::class, 1);
});

it('expires past-due invitations across owner scopes', function (): void {
    $ownerA = User::query()->create(['name' => 'Sweep A', 'email' => 'sweep-a@app.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Sweep B', 'email' => 'sweep-b@app.com', 'password' => 'secret']);
    $now = CarbonImmutable::parse('2026-09-11 12:00:00');

    $due = OwnerContext::withOwner($ownerA, fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'due@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => $now->subMinute(),
    ]));
    $upcoming = OwnerContext::withOwner($ownerB, fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'upcoming@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => $now->addMinute(),
    ]));

    $processed = OwnerContext::withOwner(null, fn (): int => app(ExpireMembershipInvitationsAction::class)->execute($now));

    expect($processed)->toBe(1)
        ->and(OwnerContext::withOwner($ownerA, fn (): MembershipInvitation => $due->fresh()))->status->toBe(InvitationStatus::Expired)
        ->and(OwnerContext::withOwner($ownerB, fn (): MembershipInvitation => $upcoming->fresh()))->status->toBe(InvitationStatus::Pending);
});

it('expires past-due invitations from the console', function (): void {
    app()->register(MembershipServiceProvider::class);

    $invitation = $this->withMembershipOwner(fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'console-sweep@example.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->subMinute(),
    ]));

    $exitCode = OwnerContext::withOwner(null, fn (): int => Artisan::call('membership:expire-invitations'));

    expect($exitCode)->toBe(0)
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and(Artisan::output())->toContain('Expired 1 membership invitation(s).');
});

it('verifies the invitation token when one is provided', function (): void {
    $acceptor = User::query()->create(['name' => 'Token Acceptor', 'email' => 'token-acceptor@app.com', 'password' => 'secret']);
    $invitation = $this->withMembershipOwner(fn (): MembershipInvitation => $this->createInvitation([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'email' => 'token-acceptor@app.com',
        'role' => MemberRole::Viewer->spatieRoleName(),
        'invited_by' => $this->inviter->getKey(),
        'expires_at' => now()->addDays(7),
    ], 'known-invitation-token'));

    expect(fn () => AcceptInvitationAction::make()->handle($invitation, $acceptor, 'wrong-token'))
        ->toThrow(RuntimeException::class, 'Invitation token is invalid.');

    expect($invitation->fresh()->accepted_at)->toBeNull();

    AcceptInvitationAction::make()->handle($invitation, $acceptor, 'known-invitation-token');

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('notifies the member hook exactly once when an application is approved', function (): void {
    $hook = new RecordingMembershipHook;
    app()->bind(MembershipHook::class, fn (): RecordingMembershipHook => $hook);

    $applicant = User::query()->create(['name' => 'Hook Applicant', 'email' => 'hook-applicant@app.com', 'password' => 'secret']);
    $reviewer = User::query()->create(['name' => 'Hook Reviewer', 'email' => 'hook-reviewer@app.com', 'password' => 'secret']);
    $application = $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Hook counting.',
    ]));

    ApproveMembershipApplicationAction::make()->handle($application, $reviewer, MemberRole::Viewer);

    expect($hook->added)->toHaveCount(1)
        ->and($hook->roleChanged)->toHaveCount(0);
});

it('notifies only the role-changed hook when a role changes', function (): void {
    $hook = new RecordingMembershipHook;
    app()->bind(MembershipHook::class, fn (): RecordingMembershipHook => $hook);

    $user = User::query()->create(['name' => 'Hook Member', 'email' => 'hook-member@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);

    expect($hook->added)->toHaveCount(1);

    ChangeMemberRoleAction::make()->handle($this->subject, $user, MemberRole::Admin);

    expect($hook->added)->toHaveCount(1)
        ->and($hook->roleChanged)->toHaveCount(1);
});

it('refuses to approve an application whose applicant is gone', function (): void {
    $reviewer = User::query()->create(['name' => 'Orphan Reviewer', 'email' => 'orphan-reviewer@app.com', 'password' => 'secret']);
    $application = $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => (string) Str::uuid(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Applicant deleted.',
    ]));

    expect(fn () => ApproveMembershipApplicationAction::make()->handle($application, $reviewer, MemberRole::Viewer))
        ->toThrow(RuntimeException::class, 'applicant no longer exists');
});

it('refuses to approve an application whose subject is gone', function (): void {
    $applicant = User::query()->create(['name' => 'Subject Orphan', 'email' => 'subject-orphan@app.com', 'password' => 'secret']);
    $reviewer = User::query()->create(['name' => 'Subject Reviewer', 'email' => 'subject-reviewer@app.com', 'password' => 'secret']);
    $application = $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => (string) Str::uuid(),
        'applicant_id' => $applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Subject deleted.',
    ]));

    expect(fn () => ApproveMembershipApplicationAction::make()->handle($application, $reviewer, MemberRole::Viewer))
        ->toThrow(RuntimeException::class, 'subject no longer exists');
});

it('removes a member even when the mapped role row is missing', function (): void {
    $user = User::query()->create(['name' => 'Legacy Member', 'email' => 'legacy-member@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);
    Role::query()->where('name', MemberRole::Viewer->spatieRoleName())->delete();

    RemoveMemberAction::make()->handle($this->subject, $user);

    expect($this->subject->members()->count())->toBe(0);
});

it('changes a role even when the previous mapped role row is missing', function (): void {
    $user = User::query()->create(['name' => 'Legacy Changer', 'email' => 'legacy-changer@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);
    Role::query()->where('name', MemberRole::Viewer->spatieRoleName())->delete();

    ChangeMemberRoleAction::make()->handle($this->subject, $user, MemberRole::Admin);

    expect($this->subject->members()->first()?->pivot->role)->toBe(MemberRole::Admin->spatieRoleName());
});

it('records which actor cancels an application', function (): void {
    $applicant = User::query()->create(['name' => 'Cancel Actor', 'email' => 'cancel-actor@app.com', 'password' => 'secret']);
    $application = $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $applicant->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Actor attribution.',
    ]));

    CancelMembershipApplicationAction::make()->handle($application, $applicant);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Cancelled)
        ->cancelled_by->toBe($applicant->getKey());
});

it('rejects invalid invitation input', function (): void {
    expect(fn () => InviteMemberAction::make()->handle($this->subject, 'not-an-email', MemberRole::Viewer, $this->inviter))
        ->toThrow(InvalidArgumentException::class, 'valid invitation email');

    expect(fn () => InviteMemberAction::make()->handle($this->subject, '', MemberRole::Viewer, $this->inviter))
        ->toThrow(InvalidArgumentException::class, 'valid invitation email');

    expect(fn () => InviteMemberAction::make()->handle($this->subject, str_repeat('a', 250) . '@example.com', MemberRole::Viewer, $this->inviter))
        ->toThrow(InvalidArgumentException::class, 'valid invitation email');

    expect(fn () => InviteMemberAction::make()->handle($this->subject, 'past@example.com', MemberRole::Viewer, $this->inviter, now()->subDay()))
        ->toThrow(InvalidArgumentException::class, 'expiry must be in the future');
});

it('rejects invalid application input', function (): void {
    $user = User::query()->create(['name' => 'Input User', 'email' => 'input-user@app.com', 'password' => 'secret']);

    expect(fn () => ApplyForMembershipAction::make()->handle($this->subject, $user, '   '))
        ->toThrow(ValueError::class, 'Justification cannot be empty.');

    expect(fn () => ApplyForMembershipAction::make()->handle($this->subject, $user, str_repeat('x', 5001)))
        ->toThrow(ValueError::class, 'must not exceed 5000 characters');

    expect(fn () => ApplyForMembershipAction::make()->handle($this->subject, $user, 'Valid.', array_fill(0, 51, 'x')))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 50 entries');

    expect(fn () => ApplyForMembershipAction::make()->handle($this->subject, $user, 'Valid.', ['blob' => str_repeat('x', 70000)]))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 65535 bytes');
});

it('rejects oversized reviewer notes', function (): void {
    $applicant = User::query()->create(['name' => 'Note Applicant', 'email' => 'note-applicant@app.com', 'password' => 'secret']);
    $secondApplicant = User::query()->create(['name' => 'Note Applicant Two', 'email' => 'note-applicant-two@app.com', 'password' => 'secret']);
    $reviewer = User::query()->create(['name' => 'Note Reviewer', 'email' => 'note-reviewer@app.com', 'password' => 'secret']);
    $makeApplication = fn (Model $for): MembershipApplication => $this->withMembershipOwner(fn (): MembershipApplication => $this->createMembershipApplication([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $for->getKey(),
        'status' => ApplicationStatus::Pending,
        'justification' => 'Note limits.',
    ]));

    expect(fn () => ApproveMembershipApplicationAction::make()->handle($makeApplication($applicant), $reviewer, MemberRole::Viewer, str_repeat('n', 5001)))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 5000 characters');

    expect(fn () => RejectMembershipApplicationAction::make()->handle($makeApplication($secondApplicant), $reviewer, str_repeat('n', 5001)))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 5000 characters');
});

it('keeps the original join date when a membership is rewritten', function (): void {
    $user = User::query()->create(['name' => 'Join Date', 'email' => 'join-date@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);

    DB::table('test_subject_members')
        ->where('test_subject_id', $this->subject->getKey())
        ->where('user_id', $user->getKey())
        ->update(['joined_at' => '2026-01-02 03:04:05']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);

    expect($this->subject->members()->first()?->pivot->joined_at)->toBe('2026-01-02 03:04:05');

    ChangeMemberRoleAction::make()->handle($this->subject, $user, MemberRole::Admin);

    expect($this->subject->members()->first()?->pivot->joined_at)->toBe('2026-01-02 03:04:05');
});

it('keeps lifecycle fields out of mass assignment', function (): void {
    $applicant = User::query()->create(['name' => 'Guarded', 'email' => 'guarded@app.com', 'password' => 'secret']);

    $application = MembershipApplication::query()->create([
        'subject_type' => $this->subject->getMorphClass(),
        'subject_id' => $this->subject->getKey(),
        'applicant_id' => $applicant->getKey(),
        'status' => ApplicationStatus::Approved,
        'granted_role' => MemberRole::Admin->spatieRoleName(),
        'justification' => 'Direct write attempt.',
    ]);

    expect($application->fresh())
        ->status->toBe(ApplicationStatus::Pending)
        ->granted_role->toBeNull();

    $bare = new MembershipApplication;
    $bare->fill(['status' => ApplicationStatus::Approved->value]);

    expect($bare->status)->toBeNull();
});

it('caches the pivot id column probe until flushed', function (): void {
    $user = User::query()->create(['name' => 'Cache Probe', 'email' => 'cache-probe@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $user, MemberRole::Viewer);

    expect($this->subject->members()->count())->toBe(1);

    Schema::table('test_subject_members', function (Blueprint $table): void {
        $table->uuid('id')->nullable();
    });

    $stale = User::query()->create(['name' => 'Cache Stale', 'email' => 'cache-stale@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $stale, MemberRole::Viewer);

    expect(DB::table('test_subject_members')->where('user_id', $stale->getKey())->value('id'))->toBeNull();

    AddMemberAction::flushPivotIdColumnCache();

    $second = User::query()->create(['name' => 'Cache Probe Two', 'email' => 'cache-probe-two@app.com', 'password' => 'secret']);

    AddMemberAction::make()->handle($this->subject, $second, MemberRole::Viewer);

    expect(DB::table('test_subject_members')->where('user_id', $second->getKey())->value('id'))->toBeUuid();
});

final class RecordingMembershipHook implements MembershipHook
{
    /** @var list<array{string, string, string}> */
    public array $added = [];

    /** @var list<array{string, string, string, string}> */
    public array $roleChanged = [];

    public function onMemberAdded(Model $subject, Model $user, MemberRole $role): void
    {
        $this->added[] = [(string) $subject->getKey(), (string) $user->getKey(), $role->value];
    }

    public function onMemberRemoved(Model $subject, Model $user, ?MemberRole $previousRole): void
    {
        //
    }

    public function onMemberRoleChanged(Model $subject, Model $user, MemberRole $oldRole, MemberRole $newRole): void
    {
        $this->roleChanged[] = [(string) $subject->getKey(), (string) $user->getKey(), $oldRole->value, $newRole->value];
    }
}
