<?php

declare(strict_types=1);

use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Api\RoleRecord;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Tests\Fixtures\TestSubject;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    request()->attributes->remove(OwnerContext::REQUEST_KEY);
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

    expect($role)->toBeInstanceOf(RoleRecord::class)
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

    $api = Mockery::mock(RoleAdministrationApi::class);
    $api->shouldReceive('permissionNames')->zeroOrMoreTimes()->andReturn([]);
    $api->shouldReceive('ensureRole')->once()->andReturn(new RoleRecord(
        'role-id',
        MemberRole::Admin->spatieRoleName(),
        'web',
        (string) $this->subject->getKey(),
    ));
    $api->shouldReceive('assignRole')->once()->andReturnUsing(function (): never {
        expect(getPermissionsTeamId())->toBe($this->subject->getKey());

        throw new RuntimeException('Assignment failed');
    });

    $this->app->instance(RoleAdministrationApi::class, $api);

    expect(fn () => $this->service->assignToUser(
        $this->subject,
        $this->user,
        MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Assignment failed');

    expect(getPermissionsTeamId())->toBe('original-team');
});

it('restores the previous team after revocation fails', function (): void {
    setPermissionsTeamId('original-team');

    $api = Mockery::mock(RoleAdministrationApi::class);
    $api->shouldReceive('revokeRole')->once()->andReturnUsing(function (): never {
        expect(getPermissionsTeamId())->toBe($this->subject->getKey());

        throw new RuntimeException('Revocation failed');
    });

    $this->app->instance(RoleAdministrationApi::class, $api);

    expect(fn () => $this->service->revokeFromUser(
        $this->subject,
        $this->user,
        MemberRole::Admin,
    ))->toThrow(RuntimeException::class, 'Revocation failed');

    expect(getPermissionsTeamId())->toBe('original-team');
});

it('requires a non-empty scalar subject key for team-scoped roles', function (): void {
    expect(fn () => $this->service->assignToUser(
        new TestSubject,
        $this->user,
        MemberRole::Admin,
    ))->toThrow(LogicException::class, 'non-empty scalar key');
});

it('ensures global roles when Spatie teams are disabled', function (): void {
    app(PermissionRegistrar::class)->teams = false;
    config()->set('permission.teams', false);

    Schema::drop('roles');
    Schema::create('roles', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    $role = $this->service->ensureExists(MemberRole::Admin);

    expect($role->name)->toBe(MemberRole::Admin->spatieRoleName())
        ->and(Schema::hasColumn('roles', 'team_id'))->toBeFalse();
});

it('clears and restores ambient team context for global role assignment', function (): void {
    config()->set('membership.features.team_scoped_roles', false);
    setPermissionsTeamId('ambient-team');

    $this->service->assignToUser($this->subject, $this->user, MemberRole::Admin);

    expect(getPermissionsTeamId())->toBe('ambient-team');

    setPermissionsTeamId(null);
    expect($this->user->hasRole(MemberRole::Admin->spatieRoleName()))->toBeTrue();
});
