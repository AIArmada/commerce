<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\PackageData;

// ============================================
// PackageData DTO Tests
// ============================================

it('creates package data with required weight', function (): void {
    $package = new PackageData(weight: 1000);

    expect($package->weight)->toBe(1000);
    expect($package->declaredValue)->toBeNull();
    expect($package->quantity)->toBe(1);
});

it('creates package data with all fields', function (): void {
    $package = new PackageData(
        weight: 2500,
        length: 30,
        width: 20,
        height: 15,
        declaredValue: 10000,
        quantity: 2,
        packagingType: 'box',
    );

    expect($package->weight)->toBe(2500);
    expect($package->length)->toBe(30);
    expect($package->width)->toBe(20);
    expect($package->height)->toBe(15);
    expect($package->declaredValue)->toBe(10000);
    expect($package->quantity)->toBe(2);
    expect($package->packagingType)->toBe('box');
});

it('calculates volumetric weight correctly', function (): void {
    $package = new PackageData(
        weight: 1000,
        length: 30,
        width: 20,
        height: 10,
    );

    // Volumetric weight = (L x W x H) / 5000 in kg, converted to grams
    // (30 x 20 x 10) / 5000 = 1.2 kg = 1200 grams
    expect($package->getVolumetricWeight())->toBe(1200);
});

it('returns actual weight when greater than volumetric', function (): void {
    $package = new PackageData(
        weight: 5000, // 5kg actual
        length: 30,
        width: 20,
        height: 10, // ~1.2kg volumetric
    );

    expect($package->getVolumetricWeight())->toBe(5000);
});

it('returns volumetric weight when greater than actual', function (): void {
    $package = new PackageData(
        weight: 500, // 0.5kg actual
        length: 50,
        width: 40,
        height: 30, // ~12kg volumetric
    );

    expect($package->getVolumetricWeight())->toBe(12000);
});

it('converts weight to kg', function (): void {
    $package = new PackageData(
        weight: 2500, // 2500 grams
    );

    expect($package->getWeightKg())->toBe(2.5);
});
