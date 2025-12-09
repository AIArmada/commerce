<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;
use AIArmada\FilamentCashierChip\FilamentCashierChipServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

it('extends spatie package service provider', function (): void {
    expect(is_subclass_of(FilamentCashierChipServiceProvider::class, PackageServiceProvider::class))->toBeTrue();
});

it('has configure package method', function (): void {
    $reflection = new ReflectionClass(FilamentCashierChipServiceProvider::class);

    expect($reflection->hasMethod('configurePackage'))->toBeTrue();
});

it('has package registered method', function (): void {
    $reflection = new ReflectionClass(FilamentCashierChipServiceProvider::class);

    expect($reflection->hasMethod('packageRegistered'))->toBeTrue();
});

it('plugin class exists', function (): void {
    expect(class_exists(FilamentCashierChipPlugin::class))->toBeTrue();
});

it('service provider class exists', function (): void {
    expect(class_exists(FilamentCashierChipServiceProvider::class))->toBeTrue();
});
