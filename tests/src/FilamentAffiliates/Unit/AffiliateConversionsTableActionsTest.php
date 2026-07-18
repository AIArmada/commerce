<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

it('blocks conversion status updates when user lacks moderation permissions', function (): void {
    $user = User::create([
        'name' => 'No Conversion Moderation Permission',
        'email' => 'no-conversion-moderation@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Conversion Authorization Affiliate',
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
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    expect(fn () => AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class))
        ->toThrow(AuthorizationException::class);
});

it('updates conversion status when user has affiliate_conversion.update permission', function (): void {
    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Conversion Moderator',
        'email' => 'conversion-moderator@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate_conversion.update');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Conversion Update Affiliate',
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
        'total_minor' => 15000,
        'value_minor' => 15000,
        'commission_minor' => 1500,
        'commission_currency' => 'USD',
    ]);

    $updated = AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class);
    $conversion->refresh();

    expect($updated)->toBeTrue()
        ->and($conversion->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($conversion->approved_at)->not->toBeNull();
});

it('updates conversion status when user has affiliate.approve permission', function (): void {
    Permission::create(['name' => 'affiliate.approve', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Affiliate Approver',
        'email' => 'affiliate-approver-conversions@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate.approve');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Conversion Approver Affiliate',
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
        'total_minor' => 12000,
        'value_minor' => 12000,
        'commission_minor' => 1200,
        'commission_currency' => 'USD',
    ]);

    $updated = AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class);
    $conversion->refresh();

    expect($updated)->toBeTrue()
        ->and($conversion->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($conversion->approved_at)->not->toBeNull();
});

it('clears approved_at when conversion is reset from approved to pending', function (): void {
    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Conversion Reset Reviewer',
        'email' => 'conversion-reset-reviewer@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate_conversion.update');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Conversion Reset Affiliate',
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
        'subtotal_minor' => 14000,
        'total_minor' => 14000,
        'value_minor' => 14000,
        'commission_minor' => 1400,
        'commission_currency' => 'USD',
    ]);

    AffiliateConversionsTable::updateStatus($conversion, ApprovedConversion::class);
    $conversion->refresh();

    expect($conversion->approved_at)->not->toBeNull();

    AffiliateConversionsTable::updateStatus($conversion, PendingConversion::class);
    $conversion->refresh();

    expect($conversion->status->equals(PendingConversion::class))->toBeTrue()
        ->and($conversion->approved_at)->toBeNull();
});

it('marks conversion as paid and retains approval timestamp semantics', function (): void {
    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Conversion Paid Marker',
        'email' => 'conversion-paid-marker@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate_conversion.update');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Conversion Paid Affiliate',
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
        'subtotal_minor' => 20000,
        'total_minor' => 20000,
        'value_minor' => 20000,
        'commission_minor' => 2000,
        'commission_currency' => 'USD',
    ]);

    $updated = AffiliateConversionsTable::updateStatus($conversion, PaidConversion::class);
    $conversion->refresh();

    expect($updated)->toBeTrue()
        ->and($conversion->status->equals(PaidConversion::class))->toBeTrue()
        ->and($conversion->approved_at)->not->toBeNull();
});
