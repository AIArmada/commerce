<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Policies\AffiliatePayoutPolicy;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Clean up any previous data
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
    User::query()->where('email', '!=', 'default-owner@example.com')->delete();
    Permission::query()->delete();
});

it('update policy returns true when user has affiliates.payout.update permission', function (): void {
    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = Affiliate::create([
        'code' => 'TEST-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
    ]);

    $policy = new AffiliatePayoutPolicy;

    expect($policy->update($user, $payout))->toBeTrue();
});

it('update policy returns false when user lacks affiliates.payout.update permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $affiliate = Affiliate::create([
        'code' => 'TEST-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
    ]);

    $policy = new AffiliatePayoutPolicy;

    expect($policy->update($user, $payout))->toBeFalse();
});

it('export policy returns true when user has affiliates.payout.export permission', function (): void {
    Permission::create(['name' => 'affiliates.payout.export', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliates.payout.export');

    $affiliate = Affiliate::create([
        'code' => 'TEST-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
    ]);

    $policy = new AffiliatePayoutPolicy;

    expect($policy->export($user, $payout))->toBeTrue();
});

it('export policy returns false when user lacks affiliates.payout.export permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $affiliate = Affiliate::create([
        'code' => 'TEST-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
    ]);

    $policy = new AffiliatePayoutPolicy;

    expect($policy->export($user, $payout))->toBeFalse();
});
