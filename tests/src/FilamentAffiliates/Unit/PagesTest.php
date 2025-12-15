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
    $reflection = new ReflectionClass(FraudReviewPage::class);
    $property = $reflection->getProperty('navigationGroup');

    expect($property->getValue())->toBe('Affiliates');
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
    $reflection = new ReflectionClass(PayoutBatchPage::class);
    $property = $reflection->getProperty('navigationGroup');

    expect($property->getValue())->toBe('Affiliates');
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
    $reflection = new ReflectionClass(ReportsPage::class);
    $property = $reflection->getProperty('navigationGroup');

    expect($property->getValue())->toBe('Affiliates');
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
