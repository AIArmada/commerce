<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;

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
