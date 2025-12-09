<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;

it('extends base cashier chip resource', function (): void {
    expect(is_subclass_of(SubscriptionResource::class, BaseCashierChipResource::class))->toBeTrue();
});

it('has model label property', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasProperty('modelLabel'))->toBeTrue();
});

it('has plural model label property', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasProperty('pluralModelLabel'))->toBeTrue();
});

it('has get model method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('getModel'))->toBeTrue();
});

it('has globally searchable attributes method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue();
});

it('has pages method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('getPages'))->toBeTrue();
});

it('has relations method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('getRelations'))->toBeTrue();
});

it('has table method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('table'))->toBeTrue();
});

it('has infolist method', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect($reflection->hasMethod('infolist'))->toBeTrue();
});
