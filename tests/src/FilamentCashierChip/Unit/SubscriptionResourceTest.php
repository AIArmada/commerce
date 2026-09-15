<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;

it('has the expected subscription resource structure', function (): void {
    $reflection = new ReflectionClass(SubscriptionResource::class);

    expect(is_subclass_of(SubscriptionResource::class, BaseCashierChipResource::class))->toBeTrue()
        ->and($reflection->hasProperty('modelLabel'))->toBeTrue()
        ->and($reflection->hasProperty('pluralModelLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getModel'))->toBeTrue()
        ->and($reflection->hasMethod('getGloballySearchableAttributes'))->toBeTrue()
        ->and($reflection->hasMethod('getPages'))->toBeTrue()
        ->and($reflection->hasMethod('getRelations'))->toBeTrue()
        ->and($reflection->hasMethod('table'))->toBeTrue()
        ->and($reflection->hasMethod('infolist'))->toBeTrue();
});
