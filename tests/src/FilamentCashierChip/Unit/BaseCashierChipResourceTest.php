<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use Filament\Resources\Resource;

it('has the expected base resource structure', function (): void {
    $reflection = new ReflectionClass(BaseCashierChipResource::class);

    expect($reflection->isSubclassOf(Resource::class))->toBeTrue()
        ->and($reflection->isAbstract())->toBeTrue()
        ->and($reflection->getMethod('navigationSortKey')->isAbstract())->toBeTrue()
        ->and($reflection->hasMethod('formatAmount'))->toBeTrue()
        ->and($reflection->hasMethod('pollingInterval'))->toBeTrue()
        ->and($reflection->hasMethod('getNavigationGroup'))->toBeTrue()
        ->and($reflection->hasMethod('getNavigationSort'))->toBeTrue()
        ->and($reflection->hasMethod('getNavigationBadge'))->toBeTrue()
        ->and($reflection->hasMethod('getNavigationBadgeColor'))->toBeTrue();
});
