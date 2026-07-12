<?php

declare(strict_types=1);

use AIArmada\Inventory\Support\InventoryOwnerScope;

describe('InventoryOwnerScope retention', function (): void {
    it('InventoryOwnerScope class is retained', function (): void {
        expect(class_exists(InventoryOwnerScope::class))->toBeTrue();
    });

    it('applyToLocationQuery scopes locations by owner', function (): void {
        $scope = new ReflectionClass(InventoryOwnerScope::class);
        expect($scope->hasMethod('applyToLocationQuery'))->toBeTrue();
        expect($scope->hasMethod('applyToQueryByLocationRelation'))->toBeTrue();
        expect($scope->hasMethod('applyToMovementQuery'))->toBeTrue();
        expect($scope->hasMethod('cacheKeySuffix'))->toBeTrue();
    });
});
