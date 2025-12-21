<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\FilamentAuthz\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;

it('scopes permission requests to the resolved owner and blocks cross-tenant writes', function (): void {
    config()->set('filament-authz.owner.enabled', true);
    config()->set('filament-authz.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'authz-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'authz-owner-b@example.com',
        'password' => 'secret',
    ]);

    $requester = User::query()->create([
        'name' => 'Requester',
        'email' => 'authz-requester@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $requestA = PermissionRequest::query()->create([
        'requester_id' => $requester->id,
        'status' => PermissionRequest::STATUS_PENDING,
    ]);

    expect(PermissionRequest::query()->whereKey($requestA->id)->exists())->toBeTrue();

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerB));

    expect(PermissionRequest::query()->whereKey($requestA->id)->exists())->toBeFalse();

    expect(function () use ($requester, $ownerA): void {
        PermissionRequest::query()->create([
            'requester_id' => $requester->id,
            'status' => PermissionRequest::STATUS_PENDING,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);
    })->toThrow(AuthorizationException::class);
});

it('scopes roles to the current owner team and blocks cross-tenant role writes', function (): void {
    config()->set('filament-authz.owner.enabled', true);
    config()->set('permission.teams', true);

    $ownerA = User::query()->create([
        'name' => 'Role Owner A',
        'email' => 'authz-role-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Role Owner B',
        'email' => 'authz-role-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $roleA = Role::create([
        'name' => 'manager',
        'guard_name' => 'web',
    ]);

    expect(Role::query()->whereKey($roleA->id)->exists())->toBeTrue();

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerB));

    expect(Role::query()->whereKey($roleA->id)->exists())->toBeFalse();

    expect(function () use ($ownerA): void {
        Role::create([
            'name' => 'auditor',
            'guard_name' => 'web',
            config('permission.column_names.team_foreign_key', 'team_id') => $ownerA->getKey(),
        ]);
    })->toThrow(AuthorizationException::class);
});
