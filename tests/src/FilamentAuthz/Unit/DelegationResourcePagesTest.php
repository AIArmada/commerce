<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\EditDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ListDelegations;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ViewDelegation;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use ReflectionClass;

describe('DelegationResource Pages', function (): void {
    describe('ListDelegations', function (): void {
        it('extends ListRecords', function (): void {
            expect(is_subclass_of(ListDelegations::class, ListRecords::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(ListDelegations::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(DelegationResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new \ReflectionMethod(ListDelegations::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });

    describe('EditDelegation', function (): void {
        it('extends EditRecord', function (): void {
            expect(is_subclass_of(EditDelegation::class, EditRecord::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(EditDelegation::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(DelegationResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new \ReflectionMethod(EditDelegation::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });

    describe('ViewDelegation', function (): void {
        it('extends ViewRecord', function (): void {
            expect(is_subclass_of(ViewDelegation::class, ViewRecord::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(ViewDelegation::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(DelegationResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new \ReflectionMethod(ViewDelegation::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });
});
