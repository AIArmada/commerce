<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;

beforeEach(function (): void {
    User::query()->delete();
    Permission::query()->delete();
});

// FraudReviewPage Tests
it('FraudReviewPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation.group' => 'Partners']);

    expect(FraudReviewPage::getNavigationGroup())->toBe('Partners');
});

// PayoutBatchPage Tests
it('PayoutBatchPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation.group' => 'Partners']);

    expect(PayoutBatchPage::getNavigationGroup())->toBe('Partners');
});

// ReportsPage Tests
it('ReportsPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation.group' => 'Partners']);

    expect(ReportsPage::getNavigationGroup())->toBe('Partners');
});

it('FraudReviewPage canAccess requires fraud review abilities', function (): void {
    $user = User::create([
        'name' => 'Fraud Reviewer',
        'email' => 'fraud-reviewer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(FraudReviewPage::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliates.fraud.update');

    expect(FraudReviewPage::canAccess())->toBeTrue();
});

it('FraudReviewPage canAccess allows affiliate.approve ability', function (): void {
    $user = User::create([
        'name' => 'Fraud Approver',
        'email' => 'fraud-approver@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(FraudReviewPage::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliate.approve', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.approve');

    expect(FraudReviewPage::canAccess())->toBeTrue();
});

it('PayoutBatchPage canAccess requires payout abilities', function (): void {
    $user = User::create([
        'name' => 'Payout Operator',
        'email' => 'payout-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(PayoutBatchPage::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliates.payout.update');

    expect(PayoutBatchPage::canAccess())->toBeTrue();
});

it('PayoutBatchPage canAccess allows affiliate.payout ability', function (): void {
    $user = User::create([
        'name' => 'Affiliate Payout Operator',
        'email' => 'affiliate-payout-operator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(PayoutBatchPage::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliate.payout', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.payout');

    expect(PayoutBatchPage::canAccess())->toBeTrue();
});

it('ReportsPage canAccess requires analytics ability', function (): void {
    $user = User::create([
        'name' => 'Affiliate Analyst',
        'email' => 'affiliate-analyst@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(ReportsPage::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliate.analytics', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.analytics');

    expect(ReportsPage::canAccess())->toBeTrue();
});
