<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use Filament\Resources\Resource;

it('extends filament resource', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->isSubclassOf(Resource::class))->toBeTrue();
});

it('is abstract class', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->isAbstract())->toBeTrue();
});

it('has navigation sort key abstract method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);
    $method = $reflection->getMethod('navigationSortKey');

    expect($method->isAbstract())->toBeTrue();
});

it('has format amount method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('formatAmount'))->toBeTrue();
});

it('has polling interval method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('pollingInterval'))->toBeTrue();
});

it('has get navigation group method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('getNavigationGroup'))->toBeTrue();
});

it('has get navigation sort method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('getNavigationSort'))->toBeTrue();
});

it('has get navigation badge method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('getNavigationBadge'))->toBeTrue();
});

it('has get navigation badge color method', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->hasMethod('getNavigationBadgeColor'))->toBeTrue();
});
