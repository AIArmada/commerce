<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Pages\BillingDashboard;
use Filament\Pages\Page;

it('extends filament page', function (): void {
    expect(is_subclass_of(BillingDashboard::class, Page::class))->toBeTrue();
});

it('has navigation icon property', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasProperty('navigationIcon'))->toBeTrue();
});

it('has slug property', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasProperty('slug'))->toBeTrue();
});

it('has header widgets method', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasMethod('getHeaderWidgets'))->toBeTrue();
});

it('has footer widgets method', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasMethod('getFooterWidgets'))->toBeTrue();
});

it('has get title method', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasMethod('getTitle'))->toBeTrue();
});

it('has get navigation label method', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect($reflection->hasMethod('getNavigationLabel'))->toBeTrue();
});
