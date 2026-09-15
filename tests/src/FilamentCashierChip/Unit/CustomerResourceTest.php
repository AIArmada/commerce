<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas\CustomerInfolist;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables\CustomerTable;
use Illuminate\Database\Eloquent\Model;

it('has the expected customer resource structure', function (): void {
    $reflection = new ReflectionClass(CustomerResource::class);

    expect($reflection->getParentClass()?->getName())->toBe(BaseCashierChipResource::class)
        ->and($reflection->hasProperty('modelLabel'))->toBeTrue()
        ->and($reflection->hasProperty('pluralModelLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getModel'))->toBeTrue()
        ->and($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue()
        ->and($reflection->hasMethod('getPages'))->toBeTrue()
        ->and($reflection->hasMethod('getRelations'))->toBeTrue()
        ->and($reflection->hasMethod('table'))->toBeTrue()
        ->and($reflection->hasMethod('infolist'))->toBeTrue();
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

it('does not throw when resolving trial state for billable models without a generic trial column', function (): void {
    $trialEndsAt = new ReflectionMethod(CustomerTable::class, 'trialEndsAt');
    $querySupport = new ReflectionMethod(CustomerTable::class, 'supportsGenericTrialQuery');

    $record = new class extends Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    expect($trialEndsAt->invoke(null, $record))->toBeNull()
        ->and($querySupport->invoke(null, $record))->toBeFalse();
});
