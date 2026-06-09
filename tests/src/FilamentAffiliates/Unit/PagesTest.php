<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use AIArmada\CommerceSupport\Models\Permission;

beforeEach(function (): void {
    User::query()->delete();
    Permission::query()->delete();
});

// FraudReviewPage Tests
it('FraudReviewPage can be instantiated', function (): void {
    $page = new FraudReviewPage;

    expect($page)->toBeInstanceOf(FraudReviewPage::class);
});

it('FraudReviewPage has correct navigation icon', function (): void {
    $reflection = new ReflectionClass(FraudReviewPage::class);
    $property = $reflection->getProperty('navigationIcon');

    expect($property->getValue())->toBe('heroicon-o-shield-exclamation');
});

it('FraudReviewPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(FraudReviewPage::getNavigationGroup())->toBe('Partners');
});

it('FraudReviewPage has correct navigation label', function (): void {
    $reflection = new ReflectionClass(FraudReviewPage::class);
    $property = $reflection->getProperty('navigationLabel');

    expect($property->getValue())->toBe('Fraud Review');
});

// PayoutBatchPage Tests
it('PayoutBatchPage can be instantiated', function (): void {
    $page = new PayoutBatchPage;

    expect($page)->toBeInstanceOf(PayoutBatchPage::class);
});

it('PayoutBatchPage has correct navigation icon', function (): void {
    $reflection = new ReflectionClass(PayoutBatchPage::class);
    $property = $reflection->getProperty('navigationIcon');

    expect($property->getValue())->toBe('heroicon-o-banknotes');
});

it('PayoutBatchPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(PayoutBatchPage::getNavigationGroup())->toBe('Partners');
});

it('PayoutBatchPage has correct navigation label', function (): void {
    $reflection = new ReflectionClass(PayoutBatchPage::class);
    $property = $reflection->getProperty('navigationLabel');

    expect($property->getValue())->toBe('Payout Batch');
});

// ReportsPage Tests
it('ReportsPage can be instantiated', function (): void {
    $page = new ReportsPage;

    expect($page)->toBeInstanceOf(ReportsPage::class);
});

it('ReportsPage has correct navigation icon', function (): void {
    $reflection = new ReflectionClass(ReportsPage::class);
    $property = $reflection->getProperty('navigationIcon');

    expect($property->getValue())->toBe('heroicon-o-chart-bar');
});

it('ReportsPage has correct navigation group', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(ReportsPage::getNavigationGroup())->toBe('Partners');
});

it('ReportsPage has correct navigation label', function (): void {
    $reflection = new ReflectionClass(ReportsPage::class);
    $property = $reflection->getProperty('navigationLabel');

    expect($property->getValue())->toBe('Reports');
});

it('ReportsPage has default period of month', function (): void {
    $page = new ReportsPage;

    expect($page->period)->toBe('month');
});

it('FraudReviewPage has navigation sort from config', function (): void {
    config(['filament-affiliates.pages.navigation_sort.fraud_review' => 25]);

    expect(FraudReviewPage::getNavigationSort())->toBe(25);
});

it('PayoutBatchPage has navigation sort from config', function (): void {
    config(['filament-affiliates.pages.navigation_sort.payout_batch' => 22]);

    expect(PayoutBatchPage::getNavigationSort())->toBe(22);
});

it('ReportsPage has navigation sort from config', function (): void {
    config(['filament-affiliates.pages.navigation_sort.reports' => 20]);

    expect(ReportsPage::getNavigationSort())->toBe(20);
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
