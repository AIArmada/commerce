<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas\CustomerInfolist;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables\CustomerTable;
use Illuminate\Database\Eloquent\Model;

it('extends base cashier chip resource', function (): void {
    expect(is_subclass_of(CustomerResource::class, BaseCashierChipResource::class))->toBeTrue();
});

it('has model label property', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasProperty('modelLabel'))->toBeTrue();
});

it('has plural model label property', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasProperty('pluralModelLabel'))->toBeTrue();
});

it('has get model method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('getModel'))->toBeTrue();
});

it('has globally searchable attributes method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue();
});

it('has pages method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('getPages'))->toBeTrue();
});

it('has relations method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('getRelations'))->toBeTrue();
});

it('has table method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('table'))->toBeTrue();
});

it('has infolist method', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->hasMethod('infolist'))->toBeTrue();
});

it('resolves subscriptions relation name for customer table with subscriptions fallback support', function (): void {
    $resolver = new ReflectionMethod(CustomerTable::class, 'resolveSubscriptionsRelationName');

    $subscriptionsModel = new class extends Model
    {
        public function subscriptions(): object
        {
            return new stdClass;
        }
    };

    $chipSubscriptionsModel = new class extends Model
    {
        public function chipSubscriptions(): object
        {
            return new stdClass;
        }
    };

    $noSubscriptionsModel = new class extends Model {};

    expect($resolver->invoke(null, $subscriptionsModel))->toBe('subscriptions')
        ->and($resolver->invoke(null, $chipSubscriptionsModel))->toBe('chipSubscriptions')
        ->and($resolver->invoke(null, $noSubscriptionsModel))->toBeNull();
});

it('resolves subscriptions relation name for customer infolist with subscriptions fallback support', function (): void {
    $resolver = new ReflectionMethod(CustomerInfolist::class, 'resolveSubscriptionsRelationName');

    $subscriptionsModel = new class extends Model
    {
        public function subscriptions(): object
        {
            return new stdClass;
        }
    };

    $chipSubscriptionsModel = new class extends Model
    {
        public function chipSubscriptions(): object
        {
            return new stdClass;
        }
    };

    $noSubscriptionsModel = new class extends Model {};

    expect($resolver->invoke(null, $subscriptionsModel))->toBe('subscriptions')
        ->and($resolver->invoke(null, $chipSubscriptionsModel))->toBe('chipSubscriptions')
        ->and($resolver->invoke(null, $noSubscriptionsModel))->toBeNull();
});
