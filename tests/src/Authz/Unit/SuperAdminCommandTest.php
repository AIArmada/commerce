<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;

it('assigns the configured super-admin role to the explicitly selected account', function (): void {
    $user = User::factory()->create();
    $roleName = (string) config('authz.super_admin_role');

    $this->artisan('authz:super-admin', ['--user' => $user->getKey()])
        ->assertSuccessful();

    expect($user->fresh()->hasRole($roleName))->toBeTrue();
});

it('rejects an invalid explicitly selected panel without granting a role', function (): void {
    $user = User::factory()->create();
    $roleName = (string) config('authz.super_admin_role');

    $this->artisan('authz:super-admin', ['--user' => $user->getKey(), '--panel' => 'missing-authz-panel'])
        ->assertFailed();

    expect($user->fresh()->hasRole($roleName))->toBeFalse();
});
