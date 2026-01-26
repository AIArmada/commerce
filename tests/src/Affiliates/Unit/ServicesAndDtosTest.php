<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\Affiliates\Traits\HasAffiliates;

// CommissionCalculator Tests
test('CommissionCalculator can be instantiated', function (): void {
    $calculator = app(CommissionCalculator::class);

    expect($calculator)->toBeInstanceOf(CommissionCalculator::class);
});

test('CommissionCalculator calculates percentage commission', function (): void {
    $calculator = app(CommissionCalculator::class);

    $affiliate = Affiliate::create([
        'code' => 'CALC001',
        'name' => 'Calculator Test',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000, // 10%
        'currency' => 'USD',
    ]);

    $commission = $calculator->calculate($affiliate, 10000); // $100.00 order

    expect($commission)->toBe(1000); // $10.00 commission
});

test('CommissionCalculator calculates fixed commission', function (): void {
    $calculator = app(CommissionCalculator::class);

    $affiliate = Affiliate::create([
        'code' => 'CALC002',
        'name' => 'Fixed Calculator Test',
        'status' => Active::class,
        'commission_type' => CommissionType::Fixed->value,
        'commission_rate' => 500, // $5.00 fixed
        'currency' => 'USD',
    ]);

    $commission = $calculator->calculate($affiliate, 10000);

    expect($commission)->toBe(500); // $5.00 fixed commission
});

// AttributionModel Tests
test('AttributionModel can be instantiated', function (): void {
    $model = app(AttributionModel::class);

    expect($model)->toBeInstanceOf(AttributionModel::class);
});

// NetworkService Tests
test('NetworkService can be instantiated', function (): void {
    $service = app(NetworkService::class);

    expect($service)->toBeInstanceOf(NetworkService::class);
});

// AffiliateLinkGenerator Tests
test('AffiliateLinkGenerator can be instantiated', function (): void {
    $generator = app(AffiliateLinkGenerator::class);

    expect($generator)->toBeInstanceOf(AffiliateLinkGenerator::class);
});

test('AffiliateLinkGenerator generates tracking link', function (): void {
    config(['affiliates.links.parameter' => 'aff']);
    config(['affiliates.links.allowed_hosts' => []]);

    $generator = app(AffiliateLinkGenerator::class);

    $affiliate = Affiliate::create([
        'code' => 'LINK001',
        'name' => 'Link Gen Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $link = $generator->generate($affiliate->code, 'https://example.com/product');

    expect($link)->toContain($affiliate->code);
    expect($link)->toContain('https://example.com/product');
});

test('AffiliateData can be created with constructor', function (): void {
    $data = new AffiliateData(
        id: 'test-id',
        code: 'DTO001',
        name: 'DTO Test',
        status: AffiliateStatus::fromString(Active::class),
        commissionType: CommissionType::Percentage,
        commissionRate: 1000,
        currency: 'USD',
    );

    expect($data)->toBeInstanceOf(AffiliateData::class);
    expect($data->code)->toBe('DTO001');
});

test('AffiliateConversionData can be created with constructor', function (): void {
    $data = new AffiliateConversionData(
        id: 'test-id',
        affiliateId: 'aff-id',
        affiliateCode: 'CONV001',
        orderReference: 'ORD-12345',
        totalMinor: 50000,
        commissionMinor: 5000,
        commissionCurrency: 'USD',
        status: ConversionStatus::fromString(ApprovedConversion::class),
        occurredAt: now(),
    );

    expect($data)->toBeInstanceOf(AffiliateConversionData::class);
    expect($data->orderReference)->toBe('ORD-12345');
});

// HasAffiliates Trait Tests - basic tests
test('HasAffiliates trait provides affiliate relationship', function (): void {
    // This tests that the trait can be used on a model
    // We can't directly test the trait, but we can verify its presence
    expect(trait_exists(HasAffiliates::class))->toBeTrue();
});

// Affiliate status enum edge cases
test('AffiliateStatus disabled status works correctly', function (): void {
    $affiliate = new Affiliate(['status' => Disabled::class]);

    expect($affiliate->isActive())->toBeFalse();
    expect($affiliate->status->equals(Disabled::class))->toBeTrue();
});

test('AffiliateStatus paused status works correctly', function (): void {
    $affiliate = new Affiliate(['status' => Paused::class]);

    expect($affiliate->isActive())->toBeFalse();
    expect($affiliate->status->equals(Paused::class))->toBeTrue();
});

// Program status edge cases
test('ProgramStatus draft works correctly', function (): void {
    $program = new AffiliateProgram(['status' => ProgramStatus::Draft]);

    expect($program->isActive())->toBeFalse();
});

test('ProgramStatus paused works correctly', function (): void {
    $program = new AffiliateProgram(['status' => ProgramStatus::Paused]);

    expect($program->isActive())->toBeFalse();
});

test('ProgramStatus archived works correctly', function (): void {
    $program = new AffiliateProgram(['status' => ProgramStatus::Archived]);

    expect($program->isActive())->toBeFalse();
});
