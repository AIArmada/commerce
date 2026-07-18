<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\FilamentAffiliates\Policies\AffiliateConversionPolicy;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

it('update policy returns true when user has affiliate_conversion.update permission', function (): void {
    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Conversion Reviewer',
        'email' => 'conversion-reviewer@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate_conversion.update');

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    $policy = new AffiliateConversionPolicy;

    expect($policy->update($user, $conversion))->toBeTrue();
});

it('update policy returns true when user has affiliate.approve permission', function (): void {
    Permission::create(['name' => 'affiliate.approve', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Affiliate Approver',
        'email' => 'affiliate-approver@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate.approve');

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 12000,
        'value_minor' => 12000,
        'commission_minor' => 1200,
        'commission_currency' => 'USD',
    ]);

    $policy = new AffiliateConversionPolicy;

    expect($policy->update($user, $conversion))->toBeTrue();
});

it('update policy returns false when user lacks conversion moderation permissions', function (): void {
    $user = User::create([
        'name' => 'No Conversion Permission',
        'email' => 'no-conversion-permission@example.com',
        'password' => bcrypt('password'),
    ]);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 15000,
        'value_minor' => 15000,
        'commission_minor' => 1500,
        'commission_currency' => 'USD',
    ]);

    $policy = new AffiliateConversionPolicy;

    expect($policy->update($user, $conversion))->toBeFalse();
});
