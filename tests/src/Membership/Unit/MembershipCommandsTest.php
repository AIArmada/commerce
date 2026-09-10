<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\MembershipServiceProvider;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Support\Facades\Artisan;

uses(MembershipTestCase::class);

beforeEach(function (): void {
    app()->register(MembershipServiceProvider::class);
});

it('syncs all membership roles via service', function (): void {
    $service = app(MembershipRoleSyncService::class);

    foreach (MemberRole::cases() as $role) {
        expect(Role::where('name', $role->spatieRoleName())->exists())->toBeFalse();
    }

    $count = $service->syncAll();

    expect($count)->toBe(count(MemberRole::cases()));

    foreach (MemberRole::cases() as $role) {
        expect(Role::where('name', $role->spatieRoleName())->exists())->toBeTrue();
    }
});

it('reconciles permissions additively unless pruning is requested', function (): void {
    $service = app(MembershipRoleSyncService::class);
    $role = $service->ensureExists(MemberRole::Admin);
    $externalPermission = Permission::findOrCreate('membership.external', 'web');

    $role->givePermissionTo($externalPermission);

    Artisan::call('membership:sync-roles');

    expect($role->fresh()->permissions->pluck('name')->all())
        ->toContain('membership.external');

    Artisan::call('membership:sync-roles', ['--prune' => true]);

    expect($role->fresh()->permissions->pluck('name')->all())
        ->not->toContain('membership.external');
});

it('does not register a host application pivot generator', function (): void {
    expect(Artisan::all())->not->toHaveKey('membership:make-pivot');
});
