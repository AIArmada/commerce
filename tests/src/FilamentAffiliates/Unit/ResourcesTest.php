<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;

// AffiliateResource Tests
it('AffiliateResource has correct model', function (): void {
    expect(AffiliateResource::getModel())->toBe(Affiliate::class);
});

it('AffiliateResource returns pages array', function (): void {
    $pages = AffiliateResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateResource has relations', function (): void {
    $relations = AffiliateResource::getRelations();

    expect($relations)->toBeArray()
        ->and(count($relations))->toBeGreaterThanOrEqual(1);
});

it('AffiliateResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliates' => 60]);

    expect(AffiliateResource::getNavigationSort())->toBe(60);
});

it('AffiliateResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Affiliates']);

    expect(AffiliateResource::getNavigationGroup())->toBe('Affiliates');
});

// AffiliateConversionResource Tests
it('AffiliateConversionResource has correct model', function (): void {
    expect(AffiliateConversionResource::getModel())->toBe(AffiliateConversion::class);
});

it('AffiliateConversionResource returns pages array', function (): void {
    $pages = AffiliateConversionResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateConversionResource has empty relations', function (): void {
    expect(AffiliateConversionResource::getRelations())->toBeArray()->toBeEmpty();
});

it('AffiliateConversionResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_conversions' => 61]);

    expect(AffiliateConversionResource::getNavigationSort())->toBe(61);
});

// AffiliatePayoutResource Tests
it('AffiliatePayoutResource has correct model', function (): void {
    expect(AffiliatePayoutResource::getModel())->toBe(AffiliatePayout::class);
});

it('AffiliatePayoutResource returns pages array', function (): void {
    $pages = AffiliatePayoutResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliatePayoutResource has relations', function (): void {
    $relations = AffiliatePayoutResource::getRelations();

    expect($relations)->toBeArray()
        ->and(count($relations))->toBeGreaterThanOrEqual(1);
});

it('AffiliatePayoutResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_payouts' => 62]);

    expect(AffiliatePayoutResource::getNavigationSort())->toBe(62);
});

// AffiliateProgramResource Tests
it('AffiliateProgramResource has correct model', function (): void {
    expect(AffiliateProgramResource::getModel())->toBe(AffiliateProgram::class);
});

it('AffiliateProgramResource returns pages array', function (): void {
    $pages = AffiliateProgramResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view')
        ->toHaveKey('edit');
});

it('AffiliateProgramResource has empty relations', function (): void {
    expect(AffiliateProgramResource::getRelations())->toBeArray()->toBeEmpty();
});

it('AffiliateProgramResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(AffiliateProgramResource::getNavigationGroup())->toBe('Partners');
});

it('AffiliateProgramResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_programs' => 73]);

    expect(AffiliateProgramResource::getNavigationSort())->toBe(73);
});

// AffiliateFraudSignalResource Tests
it('AffiliateFraudSignalResource has correct model', function (): void {
    expect(AffiliateFraudSignalResource::getModel())->toBe(AffiliateFraudSignal::class);
});

it('AffiliateFraudSignalResource returns pages array', function (): void {
    $pages = AffiliateFraudSignalResource::getPages();

    expect($pages)
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('AffiliateFraudSignalResource has empty relations', function (): void {
    expect(AffiliateFraudSignalResource::getRelations())->toBeArray()->toBeEmpty();
});

it('AffiliateFraudSignalResource has navigation badge color', function (): void {
    expect(AffiliateFraudSignalResource::getNavigationBadgeColor())->toBe('danger');
});

it('AffiliateFraudSignalResource has navigation group from config', function (): void {
    config(['filament-affiliates.navigation_group' => 'Partners']);

    expect(AffiliateFraudSignalResource::getNavigationGroup())->toBe('Partners');
});

it('AffiliateFraudSignalResource has navigation sort from config', function (): void {
    config(['filament-affiliates.resources.navigation_sort.affiliate_fraud_signals' => 74]);

    expect(AffiliateFraudSignalResource::getNavigationSort())->toBe(74);
});

it('AffiliateFraudSignalResource returns null badge when no detected signals', function (): void {
    AffiliateFraudSignal::query()->delete();

    expect(AffiliateFraudSignalResource::getNavigationBadge())->toBeNull();
});
