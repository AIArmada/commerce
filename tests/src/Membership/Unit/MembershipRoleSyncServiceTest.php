<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Model;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    $this->service = app(MembershipRoleSyncService::class);
    $this->subject = TestSubject::query()->create(['name' => 'Test Subject']);
    $this->user = User::query()->create([
        'name' => 'User',
        'email' => 'user@app.com',
        'password' => 'secret',
    ]);
});

it('ensures a role exists', function (): void {
    $role = $this->service->ensureExists(MemberRole::Admin);

    expect($role)->toBeInstanceOf(Role::class)
        ->name->toBe(MemberRole::Admin->spatieRoleName());
});

it('is idempotent when ensuring role exists', function (): void {
    $role1 = $this->service->ensureExists(MemberRole::Admin);
    $role2 = $this->service->ensureExists(MemberRole::Admin);

    expect($role1->id)->toBe($role2->id);
});

it('assigns role scoped to subject', function (): void {
    $this->service->assignToUser($this->subject, $this->user, MemberRole::Admin);

    setPermissionsTeamId($this->subject->getKey());
    expect($this->user->hasRole(MemberRole::Admin->spatieRoleName()))->toBeTrue();
    setPermissionsTeamId(null);
});

it('revokes role scoped to subject', function (): void {
    $this->service->assignToUser($this->subject, $this->user, MemberRole::Admin);
    $this->service->revokeFromUser($this->subject, $this->user, MemberRole::Admin);

    setPermissionsTeamId($this->subject->getKey());
    expect($this->user->hasRole(MemberRole::Admin->spatieRoleName()))->toBeFalse();
    setPermissionsTeamId(null);
});

it('restores the previous team after assignment fails', function (): void {
    setPermissionsTeamId('original-team');

    expect(fn () => $this->service->assignToUser(
        $this->subject,
        new ThrowingMembershipRoleUser,
        MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Assignment failed');

    expect(getPermissionsTeamId())->toBe('original-team');
});

it('restores the previous team after revocation fails', function (): void {
    setPermissionsTeamId('original-team');

    expect(fn () => $this->service->revokeFromUser(
        $this->subject,
        new ThrowingMembershipRoleUser,
        MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Revocation failed');

    expect(getPermissionsTeamId())->toBe('original-team');
});

class ThrowingMembershipRoleUser extends Model
{
    public function assignRole(mixed ...$roles): never
    {
        throw new RuntimeException('Assignment failed');
    }

    public function removeRole(mixed $role): never
    {
        throw new RuntimeException('Revocation failed');
    }
}
