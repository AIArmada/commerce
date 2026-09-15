<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;
use AIArmada\FilamentCashierChip\FilamentCashierChipServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

it('has the expected service provider structure', function (): void {
    $reflection = new ReflectionClass(FilamentCashierChipServiceProvider::class);

    expect(class_exists(FilamentCashierChipServiceProvider::class))->toBeTrue()
        ->and(class_exists(FilamentCashierChipPlugin::class))->toBeTrue()
        ->and(is_subclass_of(FilamentCashierChipServiceProvider::class, PackageServiceProvider::class))->toBeTrue()
        ->and($reflection->hasMethod('configurePackage'))->toBeTrue()
        ->and($reflection->hasMethod('packageRegistered'))->toBeTrue();
});
