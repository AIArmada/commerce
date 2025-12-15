<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Policies\AffiliatePayoutPolicy;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    // Clean up any previous data
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
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
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
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
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
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
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
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
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
    ]);

    $policy = new AffiliatePayoutPolicy;

    expect($policy->export($user, $payout))->toBeFalse();
});
