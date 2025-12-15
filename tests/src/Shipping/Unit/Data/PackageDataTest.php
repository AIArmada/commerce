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

it('returns actual weight when dimensions are missing', function (): void {
    $package = new PackageData(
        weight: 2000,
        length: 30,
        width: null,
        height: null,
    );

    expect($package->getVolumetricWeight())->toBe(2000);
});

it('checks valid dimensions when all dimensions present', function (): void {
    $package = new PackageData(
        weight: 1000,
        length: 30,
        width: 20,
        height: 10,
    );

    expect($package->hasValidDimensions())->toBeTrue();
});

it('checks invalid dimensions when any dimension missing', function (): void {
    $packageNoLength = new PackageData(
        weight: 1000,
        length: null,
        width: 20,
        height: 10,
    );

    $packageNoWidth = new PackageData(
        weight: 1000,
        length: 30,
        width: null,
        height: 10,
    );

    $packageNoHeight = new PackageData(
        weight: 1000,
        length: 30,
        width: 20,
        height: null,
    );

    expect($packageNoLength->hasValidDimensions())->toBeFalse();
    expect($packageNoWidth->hasValidDimensions())->toBeFalse();
    expect($packageNoHeight->hasValidDimensions())->toBeFalse();
});

it('calculates volumetric weight with custom divisor', function (): void {
    $package = new PackageData(
        weight: 100,
        length: 40,
        width: 30,
        height: 20,
    );

    // With divisor 6000: (40 x 30 x 20) / 6000 = 4 kg = 4000 grams
    $volumetric = $package->getVolumetricWeight(6000);

    expect($volumetric)->toBe(4000);
});
