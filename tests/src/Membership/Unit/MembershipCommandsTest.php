<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Models\Role;
use AIArmada\Membership\Console\Commands\MakePivotCommand;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

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

it('uses the membership namespace for the pivot command', function (): void {
    $command = app(MakePivotCommand::class);
    $signature = new ReflectionProperty($command, 'signature');

    expect($signature->getValue($command))->toContain('membership:make-pivot');
});
